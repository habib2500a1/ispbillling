<?php

namespace App\Services\Network;

use App\Models\Device;
use App\Services\Olt\OltSnmpProbeService;
use App\Services\Optical\CustomerOnuSmartLinkService;
use App\Services\Optical\OpticalReadingPipeline;
use App\Support\GponSnmpProfile;
use App\Support\MacAddress;
use App\Support\SnmpEnvironmentalDecoder;
use App\Support\SnmpClient;
use Illuminate\Support\Facades\Log;

/**
 * Config-driven GPON/EPON ONU SNMP sync (VSOL, ZTE, Ecom, C-DATA, Fiberhome, Nokia, Raisecom, custom).
 * OIDs: .env, gpon profile, or per-OLT meta.snmp_onu_oids.
 */
final class VsolGponOnuSyncService
{
    public function __construct(
        private readonly OltSnmpProbeService $probe,
        private readonly OpticalReadingPipeline $opticalPipeline,
    ) {}

    /**
     * @return array{success: bool, discovered: int, created: int, updated: int, linked: int, error: ?string}
     */
    public function syncOlt(Device $olt, bool $runSmartLink = false): array
    {
        $result = [
            'success' => false,
            'discovered' => 0,
            'created' => 0,
            'updated' => 0,
            'linked' => 0,
            'error' => null,
        ];

        if (! $this->supportsDriver($olt)) {
            $result['error'] = 'OLT driver has no config-driven SNMP ONU sync. Pick a vendor type or set meta.snmp_onu_oids.';

            return $result;
        }

        $profileKey = GponSnmpProfile::profileKeyForOlt($olt);
        $onuOids = GponSnmpProfile::onuOids($olt);

        $descOid = $onuOids['desc'];
        $statusOid = $onuOids['status'];
        $macOid = $onuOids['mac'];
        $rxOid = $onuOids['rx'];
        $txOid = $onuOids['tx'];
        $tempOid = $onuOids['temp'];
        $voltOid = $onuOids['voltage'];
        $snOid = $onuOids['sn'];
        $opticalScale = $onuOids['optical_scale'];

        if ($descOid === '' && $snOid === '' && $macOid === '') {
            $result['error'] = 'SNMP ONU OIDs not configured. Set VSOL_SNMP_ONU_* (or ZTE_/CDATA_/…) in .env, or OLT → meta → snmp_onu_oids (desc/mac/sn/rx).';

            return $result;
        }

        try {
            if (! SnmpClient::available()) {
                throw new \RuntimeException('PHP ext-snmp is not loaded.');
            }

            $peer = $this->probe->snmpPeer($olt);
            $community = $this->probe->effectiveCommunity($olt);
            $timeoutUs = (int) config('gpon.vsol_gpon_walk_timeout_us', 10000000);
            $retries = (int) config('snmp.retries', 1);

            $descs = $descOid !== '' ? $this->walk($peer, $community, $descOid, $timeoutUs, $retries) : [];
            $statuses = $statusOid !== '' ? $this->walk($peer, $community, $statusOid, $timeoutUs, $retries) : [];
            $macs = $macOid !== '' ? $this->walk($peer, $community, $macOid, $timeoutUs, $retries) : [];
            $rxByIdx = $rxOid !== '' ? $this->walk($peer, $community, $rxOid, $timeoutUs, $retries) : [];
            $txByIdx = $txOid !== '' ? $this->walk($peer, $community, $txOid, $timeoutUs, $retries) : [];
            $tempByIdx = $tempOid !== '' ? $this->walk($peer, $community, $tempOid, $timeoutUs, $retries) : [];
            $voltByIdx = $voltOid !== '' ? $this->walk($peer, $community, $voltOid, $timeoutUs, $retries) : [];
            $sns = $snOid !== '' ? $this->walk($peer, $community, $snOid, $timeoutUs, $retries) : [];

            $indices = array_unique(array_merge(array_keys($descs), array_keys($sns), array_keys($macs)));

            $discovered = [];
            foreach ($indices as $idx) {
                $parts = self::parseIndexSuffix($idx);
                if ($parts === null) {
                    continue;
                }

                $serial = trim((string) ($sns[$idx] ?? ''));
                $serial = preg_replace('/\s+/', '', $serial) ?? '';
                if ($serial === '') {
                    $prefix = strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $profileKey) ?: 'ONU', 0, 4));
                    $serial = $prefix.'-'.str_replace('.', '-', $idx);
                }

                $mac = MacAddress::fromSnmpValue($macs[$idx] ?? null);
                $rxRaw = $this->parseNumber($rxByIdx[$idx] ?? null);
                $rxDbm = $rxRaw !== null ? round($rxRaw / $opticalScale, 2) : null;
                $txRaw = $this->parseNumber($txByIdx[$idx] ?? null);
                $txDbm = $txRaw !== null ? round($txRaw / $opticalScale, 2) : null;

                $discovered[] = [
                    'index' => $idx,
                    'serial' => strtoupper($serial),
                    'card_no' => $parts['card_no'],
                    'pon_no' => $parts['pon_no'],
                    'onu_index' => $parts['onu_index'],
                    'label' => trim((string) ($descs[$idx] ?? '')) ?: sprintf('GPON%d/%d:%d', $parts['card_no'], $parts['pon_no'], $parts['onu_index']),
                    'mac' => $mac,
                    'oper_status' => $this->mapStatus($this->parseNumber($statuses[$idx] ?? null)),
                    'rx_dbm' => $rxDbm,
                    'tx_dbm' => $txDbm,
                    'temperature_c' => isset($tempByIdx[$idx])
                        ? SnmpEnvironmentalDecoder::temperatureC($this->parseNumber($tempByIdx[$idx]), $profileKey)
                        : null,
                    'voltage_v' => isset($voltByIdx[$idx])
                        ? SnmpEnvironmentalDecoder::voltageV($this->parseNumber($voltByIdx[$idx]), $profileKey)
                        : null,
                ];
            }

            $result['discovered'] = count($discovered);

            foreach ($discovered as $row) {
                $this->upsertOnu($olt, $row, $profileKey, $result);
            }

            if ($runSmartLink && config('optical.auto_link_on_bdcom_sync', true)) {
                $linkStats = app(CustomerOnuSmartLinkService::class)
                    ->smartRelinkTenant((int) $olt->tenant_id, true);
                $result['linked'] = (int) ($linkStats['linked'] ?? 0);
            }

            if ($result['discovered'] === 0) {
                $result['error'] = 'SNMP reachable but no ONU rows — verify OIDs (snmpwalk) for this firmware/model.';
            } else {
                $result['success'] = true;
            }
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            Log::warning('vsol_gpon_sync.failed', ['olt_id' => $olt->id, 'error' => $e->getMessage()]);
        }

        return $result;
    }

    public function supportsDriver(Device $olt): bool
    {
        return GponSnmpProfile::isConfigDrivenDriver($olt);
    }

    /**
     * @return array{card_no: int, pon_no: int, onu_index: int}|null
     */
    public static function parseIndexSuffix(string $suffix): ?array
    {
        $suffix = trim($suffix, '.');
        $parts = array_values(array_filter(explode('.', $suffix), fn ($p) => $p !== ''));

        if (count($parts) >= 4) {
            return [
                'card_no' => (int) $parts[count($parts) - 3],
                'pon_no' => (int) $parts[count($parts) - 2],
                'onu_index' => (int) $parts[count($parts) - 1],
            ];
        }

        if (count($parts) === 3) {
            return [
                'card_no' => (int) $parts[0],
                'pon_no' => (int) $parts[1],
                'onu_index' => (int) $parts[2],
            ];
        }

        if (count($parts) === 2) {
            return [
                'card_no' => 0,
                'pon_no' => (int) $parts[0],
                'onu_index' => (int) $parts[1],
            ];
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function walk(string $peer, string $community, string $oid, int $timeoutUs, int $retries): array
    {
        $out = [];
        foreach (SnmpClient::realWalk($peer, $community, $oid, $timeoutUs, $retries) as $key => $value) {
            $suffix = SnmpClient::suffixFromOidKey($key, $oid);
            if ($suffix === null) {
                continue;
            }
            $text = trim(preg_replace('/^[A-Za-z-]+:\s*/', '', (string) $value) ?? '');
            $text = trim($text, "\" \t");
            if ($text !== '') {
                $out[$suffix] = $text;
            }
        }

        return $out;
    }

    private function parseNumber(?string $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $clean = trim(preg_replace('/^[A-Za-z-]+:\s*/', '', $raw) ?? '');
        $clean = trim($clean, "\" \t");

        return is_numeric($clean) ? (int) $clean : null;
    }

    private function mapStatus(?int $code): string
    {
        return match ($code) {
            1, 5 => 'online',
            2, 3 => 'offline',
            4 => 'los',
            default => 'unknown',
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $stats
     */
    private function upsertOnu(Device $olt, array $row, string $profileKey, array &$stats): void
    {
        $serial = (string) $row['serial'];

        $onu = Device::query()
            ->withoutGlobalScopes()
            ->where('olt_id', $olt->id)
            ->where('type', 'onu')
            ->where(function ($q) use ($serial, $row): void {
                $q->where('serial_number', $serial)
                    ->orWhere('onu_external_id', (string) $row['index']);
            })
            ->first();

        $isNew = $onu === null;
        if ($isNew) {
            $onu = new Device([
                'tenant_id' => $olt->tenant_id,
                'type' => 'onu',
                'olt_id' => $olt->id,
                'serial_number' => $serial,
                'status' => 'assigned',
            ]);
            $stats['created']++;
        } else {
            $stats['updated']++;
        }

        $meta = is_array($onu->meta) ? $onu->meta : [];
        $meta['vsol_snmp_index'] = $row['index'];
        $meta['last_vsol_sync'] = now()->toIso8601String();
        if (filled($row['label'])) {
            $meta['vsol_description'] = $row['label'];
        }

        $onu->forceFill([
            'display_name' => $row['label'],
            'mac_address' => $row['mac'],
            'card_no' => $row['card_no'],
            'pon_no' => $row['pon_no'],
            'onu_index' => $row['onu_index'],
            'onu_external_id' => (string) $row['index'],
            'onu_oper_status' => $row['oper_status'],
            'gpon_profile' => $profileKey,
            'meta' => $meta,
            'last_polled_at' => now(),
        ])->save();

        if ($row['rx_dbm'] !== null || $row['tx_dbm'] !== null || ($row['temperature_c'] ?? null) !== null || ($row['voltage_v'] ?? null) !== null) {
            $this->opticalPipeline->ingest($onu->fresh(), [
                'rx_raw' => $row['rx_dbm'],
                'tx_raw' => $row['tx_dbm'],
                'temperature' => $row['temperature_c'] ?? null,
                'voltage' => $row['voltage_v'] ?? null,
                'already_dbm' => true,
                'oper_status' => $row['oper_status'],
                'vendor_profile' => $profileKey,
                'source' => 'vsol_snmp',
            ]);
        }
    }
}
