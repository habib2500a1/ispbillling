<?php

namespace App\Services\Network;

use App\Models\Device;
use App\Services\Olt\OltSnmpProbeService;
use App\Services\Optical\CustomerOnuSmartLinkService;
use App\Services\Optical\OpticalReadingPipeline;
use App\Support\MacAddress;
use App\Support\SnmpClient;
use Illuminate\Support\Facades\Log;

/**
 * Aveis GPON/EPON OLT (AV-OLT-XE08-L3, enterprise MIB 50224).
 */
final class AveisGponOnuSyncService
{
    /** First ONU index on PON1 (0x01000101). */
    private const int INDEX_BASE = 16777472;

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
            $result['error'] = 'OLT driver is not Aveis GPON/EPON.';

            return $result;
        }

        try {
            if (! SnmpClient::available()) {
                throw new \RuntimeException('PHP ext-snmp is not loaded.');
            }

            $peer = $this->probe->snmpPeer($olt);
            $community = $this->probe->effectiveCommunity($olt);
            $oids = config('gpon.profiles.aveis_gpon', []);
            $timeoutUs = (int) config('gpon.aveis_gpon_walk_timeout_us', 10000000);
            $retries = (int) config('snmp.retries', 1);

            $table = (string) ($oids['aveis_onu_table'] ?? '1.3.6.1.4.1.50224.3.3.2.1');

            $labels = $this->walkColumn($peer, $community, $table.'.2', $timeoutUs, $retries);
            $statuses = $this->walkColumn($peer, $community, $table.'.3', $timeoutUs, $retries);
            $macs = $this->walkColumn($peer, $community, $table.'.7', $timeoutUs, $retries);
            $serials = $this->walkColumn($peer, $community, $table.'.12', $timeoutUs, $retries);
            $distances = $this->walkColumn($peer, $community, $table.'.15', $timeoutUs, $retries);
            $rxRaw = $this->walkColumn($peer, $community, $table.'.16', $timeoutUs, $retries);

            $indices = array_unique(array_merge(
                array_keys($labels),
                array_keys($statuses),
                array_keys($macs),
            ));

            $discovered = [];
            foreach ($indices as $idx) {
                $parsed = self::parseAveisIndex((int) $idx);
                if ($parsed === null) {
                    continue;
                }

                $label = trim((string) ($labels[$idx] ?? ''));
                if ($label === '') {
                    $label = sprintf('PON%d:%d', $parsed['pon_no'], $parsed['onu_index']);
                }

                $mac = $this->parseMacHex($macs[$idx] ?? null);
                $equipmentId = trim((string) ($serials[$idx] ?? ''));
                $serial = 'AV-'.$idx;
                if ($mac !== null) {
                    $serial = 'AV-'.str_replace(':', '', strtoupper($mac));
                }

                $distance = $this->parseNumber($distances[$idx] ?? null);
                $rx = $this->parseAveisRx($rxRaw[$idx] ?? null);

                $discovered[] = [
                    'index' => (string) $idx,
                    'serial' => $serial,
                    'equipment_id' => $equipmentId,
                    'card_no' => $parsed['card_no'],
                    'pon_no' => $parsed['pon_no'],
                    'onu_index' => $parsed['onu_index'],
                    'label' => $label,
                    'mac' => $mac,
                    'oper_status' => $this->mapStatus($this->parseNumber($statuses[$idx] ?? null)),
                    'distance_m' => $distance,
                    'rx_dbm' => $rx,
                ];
            }

            $result['discovered'] = count($discovered);

            foreach ($discovered as $row) {
                $this->upsertOnu($olt, $row, $result);
            }

            if ($runSmartLink && config('optical.auto_link_on_bdcom_sync', true)) {
                $linkStats = app(CustomerOnuSmartLinkService::class)
                    ->smartRelinkTenant((int) $olt->tenant_id, true);
                $result['linked'] = (int) ($linkStats['linked'] ?? 0);
            }

            $result['success'] = true;
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            Log::warning('aveis_gpon_sync.failed', ['olt_id' => $olt->id, 'error' => $e->getMessage()]);
        }

        return $result;
    }

    public function supportsDriver(Device $olt): bool
    {
        $driver = strtolower((string) ($olt->olt_driver ?? ''));
        $profile = strtolower((string) ($olt->gpon_profile ?? ''));
        $vendor = strtolower((string) ($olt->vendor ?? ''));

        return in_array($driver, ['aveis_gpon', 'aveis_epon', 'aveis_xpon'], true)
            || in_array($profile, ['aveis_gpon', 'aveis_epon'], true)
            || $vendor === 'aveis';
    }

    /**
     * @return array{card_no: int, pon_no: int, onu_index: int}|null
     */
    public static function parseAveisIndex(int $idx): ?array
    {
        if ($idx <= self::INDEX_BASE) {
            return null;
        }

        $offset = $idx - self::INDEX_BASE;
        $ponNo = intdiv($offset, 256) + 1;
        $onuIndex = $offset % 256;

        if ($onuIndex < 1) {
            return null;
        }

        return [
            'card_no' => 1,
            'pon_no' => $ponNo,
            'onu_index' => $onuIndex,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function walkColumn(string $peer, string $community, string $oid, int $timeoutUs, int $retries): array
    {
        $out = [];
        foreach (SnmpClient::realWalk($peer, $community, $oid, $timeoutUs, $retries) as $key => $value) {
            $suffix = SnmpClient::suffixFromOidKey($key, $oid);
            if ($suffix === null || ! is_numeric($suffix)) {
                continue;
            }
            $out[$suffix] = trim((string) $value);
        }

        return $out;
    }

    private function parseMacHex(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $hex = preg_replace('/^Hex-STRING:\s*/i', '', $raw) ?? $raw;
        $hex = preg_replace('/\s+/', '', $hex) ?? $hex;

        if (strlen($hex) < 12) {
            return null;
        }

        return MacAddress::normalizeColon($hex);
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

    /**
     * Aveis col.16 — tentatively 0.1 dBm (negative RX); tune via OPTICAL_AVEIS_RX_MODE if needed.
     */
    private function parseAveisRx(?string $raw): ?float
    {
        $n = $this->parseNumber($raw);
        if ($n === null || $n === 0) {
            return null;
        }

        $mode = (string) config('gpon.aveis_rx_mode', 'negative_tenth');

        return match ($mode) {
            'negative_tenth' => ($n > 0 && $n < 150) ? round(-$n / 10, 2) : null,
            'tenth_dbm' => round($n / 10, 2),
            'skip' => null,
            default => null,
        };
    }

    private function mapStatus(?int $code): string
    {
        return match ($code) {
            1 => 'online',
            2 => 'offline',
            3 => 'los',
            default => 'unknown',
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $stats
     */
    private function upsertOnu(Device $olt, array $row, array &$stats): void
    {
        $serial = (string) $row['serial'];

        $onu = Device::query()
            ->withoutGlobalScopes()
            ->where('olt_id', $olt->id)
            ->where('type', 'onu')
            ->where(function ($q) use ($serial, $row): void {
                $q->where('onu_external_id', (string) $row['index'])
                    ->orWhere('serial_number', $serial);
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
        $meta['aveis_snmp_index'] = $row['index'];
        $meta['aveis_label'] = $row['label'];
        $meta['last_aveis_sync'] = now()->toIso8601String();
        if ($row['distance_m'] !== null) {
            $meta['distance_m'] = $row['distance_m'];
            $meta['aveis_distance_m'] = $row['distance_m'];
        }

        $description = trim((string) $row['label']);
        if ($description !== '') {
            $meta['aveis_description'] = $description;
        }
        if (filled($row['equipment_id'] ?? null)) {
            $meta['aveis_equipment_id'] = $row['equipment_id'];
        }

        $onu->forceFill([
            'display_name' => $row['label'],
            'mac_address' => $row['mac'],
            'card_no' => $row['card_no'],
            'pon_no' => $row['pon_no'],
            'onu_index' => $row['onu_index'],
            'onu_external_id' => (string) $row['index'],
            'onu_oper_status' => $row['oper_status'],
            'gpon_profile' => 'aveis_gpon',
            'meta' => $meta,
            'last_polled_at' => now(),
        ])->save();

        if ($row['rx_dbm'] !== null) {
            $this->opticalPipeline->ingest($onu->fresh(), [
                'rx_raw' => $row['rx_dbm'],
                'already_dbm' => true,
                'oper_status' => $row['oper_status'],
                'vendor_profile' => 'aveis_gpon',
                'source' => 'aveis_snmp',
                'bypass_smoothing' => true,
            ]);
        }
    }
}
