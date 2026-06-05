<?php

namespace App\Services\Network;

use App\Models\Device;
use App\Support\MacAddress;
use App\Support\SnmpClient;
use Illuminate\Support\Facades\Log;

/**
 * Auto-detect Aveis enterprise ONU table column numbers (MAC, status, RX, label, name)
 * by SNMP-walking candidate columns and scoring sample values — no manual .env required.
 *
 * Results are cached on the OLT device meta (aveis_snmp_column_map) for 30 days or until
 * a sync with cached columns discovers zero ONUs (then re-probe).
 */
final class AveisSnmpColumnDiscoveryService
{
    /**
     * @param  array<string, mixed>  $profileOids  merged gpon profile for this OLT
     * @return array<string, mixed>  profile OIDs with resolved *_column keys
     */
    public function resolveColumns(Device $olt, array $profileOids, string $peer, string $community, int $timeoutUs, int $retries, bool $forceProbe = false): array
    {
        $table = (string) ($profileOids['aveis_onu_table'] ?? '1.3.6.1.4.1.50224.3.3.2.1');
        $cached = $this->cachedMap($olt);

        if (! $forceProbe && $cached !== null && ! $this->cacheExpired($cached)) {
            return $this->applyMap($profileOids, $cached);
        }

        $discovered = $this->probeColumns($peer, $community, $table, $timeoutUs, $retries);
        if ($discovered === null) {
            return $profileOids;
        }

        $this->persistMap($olt, $discovered);

        return $this->applyMap($profileOids, $discovered);
    }

    /**
     * Re-probe when inventory sync with current columns found no parseable ONUs.
     */
    public function shouldReprobeAfterEmptySync(Device $olt, int $discoveredCount): bool
    {
        if ($discoveredCount > 0) {
            return false;
        }

        return $this->cachedMap($olt) !== null;
    }

    /**
     * @param  array<string, mixed>  $map
     * @return array<string, mixed>
     */
    public function applyMap(array $profileOids, array $map): array
    {
        $keys = [
            'aveis_onu_label_column' => 'label',
            'aveis_onu_status_column' => 'status',
            'aveis_onu_mac_column' => 'mac',
            'aveis_onu_name_column' => 'name',
            'aveis_onu_rx_column' => 'rx',
            'aveis_onu_tx_column' => 'tx',
            'aveis_onu_temp_column' => 'temp',
            'aveis_onu_voltage_column' => 'voltage',
            'aveis_onu_distance_column' => 'distance',
        ];

        foreach ($keys as $oidKey => $mapKey) {
            if (isset($map[$mapKey]) && (int) $map[$mapKey] > 0) {
                $profileOids[$oidKey] = (int) $map[$mapKey];
            }
        }

        return $profileOids;
    }

    /**
     * @return array<string, int|float|string>|null
     */
    public function probeColumns(string $peer, string $community, string $table, int $timeoutUs, int $retries): ?array
    {
        if (! SnmpClient::available()) {
            return null;
        }

        $maxCol = max(8, min(30, (int) config('gpon.aveis_column_probe_max', 18)));
        $minScore = (float) config('gpon.aveis_column_probe_min_score', 0.35);

        $samples = [];
        for ($col = 1; $col <= $maxCol; $col++) {
            $values = $this->walkColumn($peer, $community, $table.'.'.$col, $timeoutUs, $retries);
            if ($values === []) {
                continue;
            }
            $samples[$col] = array_values($values);
        }

        if ($samples === []) {
            return null;
        }

        $macCol = $this->pickBestColumn($samples, fn (array $v): float => self::scoreMacColumn($v), $minScore);
        $statusCol = $this->pickBestColumn($samples, fn (array $v): float => self::scoreStatusColumn($v), $minScore);
        $rxCol = $this->pickBestColumn($samples, fn (array $v): float => self::scoreRxColumn($v), $minScore * 0.8);
        $labelCol = $this->pickBestColumn($samples, fn (array $v): float => self::scoreLabelColumn($v), $minScore);
        $nameCol = $this->pickBestColumn($samples, fn (array $v): float => self::scoreNameColumn($v), $minScore);

        if ($macCol === null && $statusCol === null) {
            return null;
        }

        $map = array_filter([
            'label' => $labelCol ?? 2,
            'status' => $statusCol ?? 3,
            'mac' => $macCol ?? 7,
            'name' => $nameCol ?? 12,
            'rx' => $rxCol ?? (int) config('gpon.aveis_onu_rx_column', 15),
            'tx' => (int) config('gpon.aveis_onu_tx_column', 0),
            'temp' => (int) config('gpon.aveis_onu_temp_column', 18),
            'voltage' => (int) config('gpon.aveis_onu_voltage_column', 19),
            'distance' => (int) config('gpon.aveis_onu_distance_column', 0),
            'probed_at' => now()->toIso8601String(),
            'table' => $table,
        ], fn ($v): bool => $v !== null && $v !== 0 || is_string($v));

        Log::info('aveis_snmp_columns.discovered', [
            'peer' => $peer,
            'map' => $map,
        ]);

        return $map;
    }

    /**
     * @param  array<int, list<string>>  $samples
     */
    private function pickBestColumn(array $samples, callable $scorer, float $minScore): ?int
    {
        $bestCol = null;
        $bestScore = 0.0;

        foreach ($samples as $col => $values) {
            $score = $scorer($values);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCol = (int) $col;
            }
        }

        return ($bestCol !== null && $bestScore >= $minScore) ? $bestCol : null;
    }

    /**
     * @param  list<string>  $values
     */
    public static function scoreMacColumn(array $values): float
    {
        $total = count($values);
        if ($total === 0) {
            return 0.0;
        }

        $hits = 0;
        foreach ($values as $raw) {
            if (MacAddress::fromSnmpValue($raw) !== null) {
                $hits++;
            }
        }

        return $hits / $total;
    }

    /**
     * @param  list<string>  $values
     */
    public static function scoreStatusColumn(array $values): float
    {
        $total = count($values);
        if ($total === 0) {
            return 0.0;
        }

        $hits = 0;
        foreach ($values as $raw) {
            $n = self::parseInt($raw);
            if ($n !== null && $n >= 1 && $n <= 5) {
                $hits++;
            }
        }

        return $hits / $total;
    }

    /**
     * @param  list<string>  $values
     */
    public static function scoreRxColumn(array $values): float
    {
        $total = count($values);
        if ($total === 0) {
            return 0.0;
        }

        $hits = 0;
        foreach ($values as $raw) {
            $n = self::parseInt($raw);
            if ($n === null || $n === 0) {
                continue;
            }
            $dbm = AveisGponOnuSyncService::decodeAveisRx($n);
            if ($dbm !== null && $dbm >= -35.0 && $dbm <= -3.0) {
                $hits++;
            }
        }

        return $hits / $total;
    }

    /**
     * @param  list<string>  $values
     */
    public static function scoreLabelColumn(array $values): float
    {
        $total = count($values);
        if ($total === 0) {
            return 0.0;
        }

        $hits = 0;
        foreach ($values as $raw) {
            $text = trim((string) $raw);
            if ($text === '' || MacAddress::fromSnmpValue($raw) !== null) {
                continue;
            }
            if (strlen($text) >= 2 && strlen($text) <= 64 && ! preg_match('/^\d+$/', $text)) {
                $hits++;
            }
        }

        return $hits / $total;
    }

    /**
     * Equipment / subscriber name column (often PPP login or customer code).
     *
     * @param  list<string>  $values
     */
    public static function scoreNameColumn(array $values): float
    {
        $total = count($values);
        if ($total === 0) {
            return 0.0;
        }

        $hits = 0;
        foreach ($values as $raw) {
            $text = trim((string) $raw);
            if ($text === '' || MacAddress::fromSnmpValue($raw) !== null) {
                continue;
            }
            if (strlen($text) >= 3 && strlen($text) <= 48 && preg_match('/[A-Za-z]/', $text)) {
                $hits++;
            }
        }

        return $hits / $total;
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

    /**
     * @return array<string, mixed>|null
     */
    private function cachedMap(Device $olt): ?array
    {
        $meta = is_array($olt->meta) ? $olt->meta : [];
        $map = $meta['aveis_snmp_column_map'] ?? null;

        return is_array($map) ? $map : null;
    }

    /**
     * @param  array<string, mixed>  $map
     */
    private function cacheExpired(array $map): bool
    {
        $probedAt = $map['probed_at'] ?? null;
        if (! is_string($probedAt) || $probedAt === '') {
            return true;
        }

        try {
            $ageDays = (int) config('gpon.aveis_column_map_ttl_days', 30);

            return now()->diffInDays(\Illuminate\Support\Carbon::parse($probedAt)) >= $ageDays;
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @param  array<string, mixed>  $map
     */
    private function persistMap(Device $olt, array $map): void
    {
        $meta = is_array($olt->meta) ? $olt->meta : [];
        $meta['aveis_snmp_column_map'] = $map;
        $olt->forceFill(['meta' => $meta])->saveQuietly();
        $olt->refresh();
    }

    private static function parseInt(?string $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $clean = trim(preg_replace('/^[A-Za-z-]+:\s*/', '', $raw) ?? '');
        $clean = trim($clean, "\" \t");

        return is_numeric($clean) ? (int) $clean : null;
    }
}
