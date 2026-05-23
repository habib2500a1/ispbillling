<?php

namespace App\Services\Network;

use App\Models\Device;
use App\Services\Olt\OltSnmpProbeService;
use App\Services\Optical\CustomerOnuSmartLinkService;
use App\Services\Optical\OpticalReadingPipeline;
use App\Support\CustomerPppLoginResolver;
use App\Support\SnmpClient;
use Illuminate\Support\Facades\Log;

/**
 * Huawei MA5800 / MA5600 GPON optical SNMP inventory (HUAWEI-GPON-MIB).
 */
final class HuaweiGponOnuSyncService
{
    private const int INVALID_OPTICAL = 2147483647;

    public function __construct(
        private readonly OltSnmpProbeService $probe,
        private readonly OpticalReadingPipeline $opticalPipeline,
    ) {}

    /**
     * @return array{success: bool, discovered: int, created: int, updated: int, linked: int, error: ?string}
     */
    public function syncOlt(Device $olt): array
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
            $result['error'] = 'OLT driver is not Huawei GPON.';

            return $result;
        }

        try {
            if (! SnmpClient::available()) {
                throw new \RuntimeException('PHP ext-snmp is not loaded.');
            }

            $peer = $this->probe->snmpPeer($olt);
            $community = $this->probe->effectiveCommunity($olt);
            $oids = config('gpon.profiles.huawei_gpon', []);
            $timeoutUs = (int) config('gpon.huawei_gpon_walk_timeout_us', 12000000);
            $retries = (int) config('snmp.retries', 1);

            $rxByIdx = $this->walkOptical($peer, $community, (string) ($oids['huawei_gpon_onu_rx'] ?? ''), $timeoutUs, $retries);
            $txByIdx = $this->walkOptical($peer, $community, (string) ($oids['huawei_gpon_onu_tx'] ?? ''), $timeoutUs, $retries);
            $stateByIdx = $this->walkIntegers($peer, $community, (string) ($oids['huawei_gpon_onu_run_state'] ?? ''), $timeoutUs, $retries);
            $snByIdx = $this->walkStrings($peer, $community, (string) ($oids['huawei_gpon_onu_sn'] ?? ''), $timeoutUs, $retries);
            $distByIdx = $this->walkIntegers($peer, $community, (string) ($oids['huawei_gpon_onu_distance'] ?? ''), $timeoutUs, $retries);

            $indices = array_unique(array_merge(
                array_keys($rxByIdx),
                array_keys($txByIdx),
                array_keys($stateByIdx),
                array_keys($snByIdx),
            ));

            $discovered = [];
            foreach ($indices as $idx) {
                $parts = self::parseHuaweiIndex($idx);
                if ($parts === null) {
                    continue;
                }

                $serial = $snByIdx[$idx] ?? null;
                $serial = $serial !== null && $serial !== '' ? preg_replace('/\s+/', '', $serial) : null;
                if ($serial === null || $serial === '') {
                    $serial = 'HW-'.str_replace('.', '-', $idx);
                }

                $discovered[] = [
                    'index' => $idx,
                    'serial' => strtoupper($serial),
                    'card_no' => $parts['card_no'],
                    'pon_no' => $parts['pon_no'],
                    'onu_index' => $parts['onu_index'],
                    'label' => sprintf('GPON%d/%d:%d', $parts['card_no'], $parts['pon_no'], $parts['onu_index']),
                    'rx_dbm' => $rxByIdx[$idx] ?? null,
                    'tx_dbm' => $txByIdx[$idx] ?? null,
                    'oper_status' => $this->mapRunState($stateByIdx[$idx] ?? null),
                    'distance_m' => $distByIdx[$idx] ?? null,
                ];
            }

            $result['discovered'] = count($discovered);

            foreach ($discovered as $row) {
                $this->upsertOnu($olt, $row, $result);
            }

            if (config('optical.auto_link_on_bdcom_sync', true)) {
                $linkStats = app(CustomerOnuSmartLinkService::class)
                    ->smartRelinkTenant((int) $olt->tenant_id, true);
                $result['linked'] = $linkStats['linked'];
            }

            $result['success'] = true;
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            Log::warning('huawei_gpon_sync.failed', ['olt_id' => $olt->id, 'error' => $e->getMessage()]);
        }

        return $result;
    }

    public function supportsDriver(Device $olt): bool
    {
        $driver = strtolower((string) ($olt->olt_driver ?? ''));
        $profile = strtolower((string) ($olt->gpon_profile ?? ''));
        $vendor = strtolower((string) ($olt->vendor ?? ''));

        return $driver === 'huawei_gpon'
            || $profile === 'huawei_gpon'
            || $vendor === 'huawei';
    }

    /**
     * @return array{card_no: int, pon_no: int, onu_index: int}|null
     */
    public static function parseHuaweiIndex(string $suffix): ?array
    {
        $suffix = trim($suffix, '.');
        if (! preg_match('/^(\d+)\.(\d+)\.(\d+)\.(\d+)$/', $suffix, $m)) {
            return null;
        }

        return [
            'card_no' => (int) $m[2],
            'pon_no' => (int) $m[3],
            'onu_index' => (int) $m[4],
        ];
    }

    /**
     * @return array<string, float>
     */
    private function walkOptical(string $peer, string $community, string $oid, int $timeoutUs, int $retries): array
    {
        if ($oid === '') {
            return [];
        }

        $out = [];
        foreach (SnmpClient::realWalk($peer, $community, $oid, $timeoutUs, $retries) as $key => $value) {
            $suffix = SnmpClient::suffixFromOidKey($key, $oid);
            if ($suffix === null) {
                continue;
            }
            $dbm = $this->toDbm($value);
            if ($dbm !== null) {
                $out[$suffix] = $dbm;
            }
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    private function walkIntegers(string $peer, string $community, string $oid, int $timeoutUs, int $retries): array
    {
        if ($oid === '') {
            return [];
        }

        $out = [];
        foreach (SnmpClient::realWalk($peer, $community, $oid, $timeoutUs, $retries) as $key => $value) {
            $suffix = SnmpClient::suffixFromOidKey($key, $oid);
            if ($suffix === null) {
                continue;
            }
            $n = $this->parseNumber($value);
            if ($n !== null) {
                $out[$suffix] = (int) $n;
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function walkStrings(string $peer, string $community, string $oid, int $timeoutUs, int $retries): array
    {
        if ($oid === '') {
            return [];
        }

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

    private function toDbm(mixed $raw): ?float
    {
        $n = $this->parseNumber($raw);
        if ($n === null || (int) $n === self::INVALID_OPTICAL || $n > 100000) {
            return null;
        }

        if (abs($n) > 60) {
            return round($n / 100, 2);
        }

        return round($n, 2);
    }

    private function parseNumber(mixed $raw): ?float
    {
        $clean = trim(preg_replace('/^[A-Za-z-]+:\s*/', '', (string) $raw) ?? '');
        $clean = trim($clean, "\" \t");

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function mapRunState(?int $code): string
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
    private function upsertOnu(Device $olt, array $row, array &$stats): void
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
        $meta['huawei_gpon_index'] = $row['index'];
        $meta['last_huawei_sync'] = now()->toIso8601String();

        $onu->forceFill([
            'display_name' => $row['label'],
            'card_no' => $row['card_no'],
            'pon_no' => $row['pon_no'],
            'onu_index' => $row['onu_index'],
            'onu_oper_status' => $row['oper_status'],
            'gpon_profile' => 'huawei_gpon',
            'meta' => $meta,
        ])->save();

        if ($row['rx_dbm'] !== null || $row['tx_dbm'] !== null) {
            $this->opticalPipeline->ingest($onu->fresh(), [
                'rx_raw' => $row['rx_dbm'],
                'tx_raw' => $row['tx_dbm'],
                'oper_status' => $row['oper_status'],
                'vendor_profile' => 'huawei_gpon',
                'source' => 'huawei_snmp',
            ]);
        }
    }
}
