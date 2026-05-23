<?php

namespace App\Services\Olt;

use App\Models\Device;
use App\Models\OltHealthLog;
use App\Support\SnmpClient;
use Illuminate\Support\Facades\Schema;

/**
 * Polls OLT CPU/RAM/temperature via HOST-RESOURCES-MIB and vendor OIDs.
 */
final class OltHealthProbeService
{
    public function __construct(
        private readonly OltSnmpProbeService $probe,
    ) {}

    /**
     * @param  array<string, mixed>  $pollContext  From OltSnmpMonitorService (interfaces, onus, uptime)
     * @return array<string, mixed>
     */
    public function probeAndPersist(Device $olt, array $pollContext = []): array
    {
        $profile = $this->resolveProfile($olt);
        $oids = $this->oidsForProfile($profile);

        $result = [
            'profile' => $profile,
            'snmp_ok' => false,
            'cpu_percent' => null,
            'memory_percent' => null,
            'temperature_c' => null,
            'fan_status' => null,
            'power_supply_status' => null,
            'health_score' => null,
            'error' => null,
        ];

        try {
            if (($olt->snmp_version ?? 'v2c') !== 'v2c') {
                throw new \RuntimeException('OLT health SNMP supports v2c only.');
            }

            if (! SnmpClient::available()) {
                throw new \RuntimeException('PHP ext-snmp is not loaded.');
            }

            $peer = $this->probe->snmpPeer($olt);
            $community = $this->probe->effectiveCommunity($olt);

            $cpu = $this->pollCpu($peer, $community, $oids);
            $memory = $this->pollMemory($peer, $community, $oids);
            $temperature = $this->pollTemperature($peer, $community, $oids);

            $result['cpu_percent'] = $cpu;
            $result['memory_percent'] = $memory;
            $result['temperature_c'] = $temperature;
            $result['snmp_ok'] = $cpu !== null || $memory !== null || $temperature !== null;
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        $meta = $this->mergeMetaOverrides($olt, $result);
        $result = array_merge($result, $meta);

        $result['health_score'] = $this->computeHealthScore($result);
        $result['fan_status'] = $result['fan_status'] ?? $this->inferFanStatus($result);
        $result['power_supply_status'] = $result['power_supply_status'] ?? 'unknown';

        $health = array_merge(is_array($olt->olt_health) ? $olt->olt_health : [], [
            'cpu_percent' => $result['cpu_percent'],
            'memory_percent' => $result['memory_percent'],
            'temperature_c' => $result['temperature_c'],
            'fan_status' => $result['fan_status'],
            'power_supply_status' => $result['power_supply_status'],
            'health_score' => $result['health_score'],
            'health_profile' => $profile,
            'health_polled_at' => now()->toIso8601String(),
            'health_snmp_ok' => $result['snmp_ok'] || filled($meta['cpu_percent'] ?? null),
        ], array_filter([
            'onus_online' => $pollContext['onus_online'] ?? null,
            'onus_offline' => $pollContext['onus_offline'] ?? null,
            'interfaces_up' => $pollContext['interfaces_up'] ?? null,
            'interfaces_total' => $pollContext['interfaces_total'] ?? null,
            'sys_uptime_ticks' => $pollContext['sys_uptime_ticks'] ?? null,
        ], fn ($v) => $v !== null));

        $olt->forceFill(['olt_health' => $health])->save();

        if (Schema::hasTable('olt_health_logs')) {
            OltHealthLog::query()->create([
                'tenant_id' => $olt->tenant_id,
                'device_id' => $olt->id,
                'snmp_ok' => (bool) ($result['snmp_ok'] || filled($result['cpu_percent'])),
                'cpu_percent' => $result['cpu_percent'],
                'memory_percent' => $result['memory_percent'],
                'temperature_c' => $result['temperature_c'],
                'fan_status' => $result['fan_status'],
                'power_supply_status' => $result['power_supply_status'],
                'interfaces_up' => $pollContext['interfaces_up'] ?? null,
                'interfaces_total' => $pollContext['interfaces_total'] ?? null,
                'onus_online' => $pollContext['onus_online'] ?? null,
                'onus_offline' => $pollContext['onus_offline'] ?? null,
                'pon_ports' => $pollContext['pon_ports'] ?? $olt->ports()->count(),
                'sys_uptime_ticks' => $pollContext['sys_uptime_ticks'] ?? null,
                'health_score' => $result['health_score'],
                'metrics' => [
                    'profile' => $profile,
                    'error' => $result['error'],
                ],
                'sampled_at' => now(),
            ]);
        }

        return $result;
    }

    public function resolveProfile(Device $olt): string
    {
        $vendor = strtolower((string) ($olt->vendor ?? ''));
        $mapped = config("olt_health.vendor_to_profile.{$vendor}");

        if (is_string($mapped) && $mapped !== '') {
            return $mapped;
        }

        return (string) config('olt_health.default_profile', 'host_resources');
    }

    /**
     * @return array<string, mixed>
     */
    public function oidsForProfile(string $profile): array
    {
        $profiles = config('olt_health.profiles', []);
        $cfg = $profiles[$profile] ?? $profiles['host_resources'] ?? [];

        if (isset($cfg['extends'])) {
            $parent = $profiles[$cfg['extends']] ?? [];
            $cfg = array_merge($parent, $cfg);
        }

        return $cfg;
    }

    /**
     * @param  array<string, mixed>  $oids
     */
    private function pollCpu(string $peer, string $community, array $oids): ?int
    {
        if (isset($oids['cpu_usage'])) {
            $walk = SnmpClient::walk($peer, $community, (string) $oids['cpu_usage']);
            $max = $this->maxNumericWalk($walk);
            if ($max !== null) {
                return $max;
            }
        }

        if (! isset($oids['hr_processor_load'])) {
            return null;
        }

        $walk = SnmpClient::walk($peer, $community, (string) $oids['hr_processor_load']);

        return $this->averageNumericWalk($walk);
    }

    /**
     * @param  array<string, mixed>  $oids
     */
    private function pollMemory(string $peer, string $community, array $oids): ?int
    {
        if (isset($oids['memory_usage'])) {
            $walk = SnmpClient::walk($peer, $community, (string) $oids['memory_usage']);
            $max = $this->maxNumericWalk($walk);
            if ($max !== null) {
                return $max;
            }
        }

        if (! isset($oids['hr_storage_descr'])) {
            return null;
        }

        $descrWalk = SnmpClient::walk($peer, $community, (string) $oids['hr_storage_descr']);
        $usedWalk = SnmpClient::walk($peer, $community, (string) $oids['hr_storage_used']);
        $sizeWalk = SnmpClient::walk($peer, $community, (string) $oids['hr_storage_size']);
        $unitsWalk = SnmpClient::walk($peer, $community, (string) $oids['hr_storage_allocation_units']);

        $matchTerms = array_map('strtolower', $oids['memory_descr_match'] ?? ['physical memory', 'memory']);

        foreach ($descrWalk as $oidKey => $descr) {
            $descrLower = strtolower((string) $descr);
            $matches = false;
            foreach ($matchTerms as $term) {
                if (str_contains($descrLower, $term)) {
                    $matches = true;
                    break;
                }
            }
            if (! $matches) {
                continue;
            }

            $suffix = SnmpClient::suffixFromOidKey($oidKey, (string) $oids['hr_storage_descr']);
            if ($suffix === null) {
                continue;
            }

            $used = $this->valueBySuffix($usedWalk, $suffix, (string) $oids['hr_storage_used']);
            $size = $this->valueBySuffix($sizeWalk, $suffix, (string) $oids['hr_storage_size']);
            if ($used === null || $size === null || (float) $size <= 0) {
                continue;
            }

            return (int) min(100, round(((float) $used / (float) $size) * 100));
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $oids
     */
    private function pollTemperature(string $peer, string $community, array $oids): ?float
    {
        if (! isset($oids['temperature'])) {
            return null;
        }

        $walk = SnmpClient::walk($peer, $community, (string) $oids['temperature']);
        $max = null;
        foreach ($walk as $value) {
            if (! is_numeric($value)) {
                continue;
            }
            $v = (float) $value;
            if ($v > 200) {
                $v = $v / 10;
            }
            $max = $max === null ? $v : max($max, $v);
        }

        return $max;
    }

    /**
     * @param  array<string, string>  $walk
     */
    private function averageNumericWalk(array $walk): ?int
    {
        $values = [];
        foreach ($walk as $v) {
            if (is_numeric($v)) {
                $values[] = (float) $v;
            }
        }

        if ($values === []) {
            return null;
        }

        return (int) min(100, round(array_sum($values) / count($values)));
    }

    /**
     * @param  array<string, string>  $walk
     */
    private function maxNumericWalk(array $walk): ?int
    {
        $max = null;
        foreach ($walk as $v) {
            if (! is_numeric($v)) {
                continue;
            }
            $n = (int) min(100, round((float) $v));
            $max = $max === null ? $n : max($max, $n);
        }

        return $max;
    }

    /**
     * @param  array<string, string>  $walk
     */
    private function valueBySuffix(array $walk, string $suffix, string $baseOid): ?float
    {
        foreach ($walk as $oidKey => $value) {
            if (SnmpClient::suffixFromOidKey($oidKey, $baseOid) === $suffix && is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function mergeMetaOverrides(Device $olt, array $result): array
    {
        $meta = is_array($olt->meta) ? $olt->meta : [];
        $keys = config('olt_health.meta_keys', []);
        $out = [];

        foreach (['cpu_percent', 'memory_percent', 'temperature_c', 'fan_status', 'power_supply_status'] as $field) {
            $aliases = $keys[$field] ?? [];
            foreach ($aliases as $alias) {
                if (! isset($meta[$alias]) || $meta[$alias] === '') {
                    continue;
                }
                $out[$field] = $meta[$alias];
                break;
            }
        }

        if (isset($out['cpu_percent']) && is_numeric($out['cpu_percent'])) {
            $out['cpu_percent'] = (int) min(100, round((float) $out['cpu_percent']));
        }
        if (isset($out['memory_percent']) && is_numeric($out['memory_percent'])) {
            $out['memory_percent'] = (int) min(100, round((float) $out['memory_percent']));
        }
        if (isset($out['temperature_c']) && is_numeric($out['temperature_c'])) {
            $out['temperature_c'] = round((float) $out['temperature_c'], 1);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function computeHealthScore(array $metrics): int
    {
        $thresholds = config('olt_health.thresholds', []);
        $score = 100;

        $cpu = $metrics['cpu_percent'] ?? null;
        if ($cpu !== null) {
            $score -= match (true) {
                $cpu >= ($thresholds['cpu_critical'] ?? 90) => 35,
                $cpu >= ($thresholds['cpu_warning'] ?? 75) => 18,
                default => 0,
            };
        }

        $mem = $metrics['memory_percent'] ?? null;
        if ($mem !== null) {
            $score -= match (true) {
                $mem >= ($thresholds['memory_critical'] ?? 92) => 30,
                $mem >= ($thresholds['memory_warning'] ?? 80) => 15,
                default => 0,
            };
        }

        $temp = $metrics['temperature_c'] ?? null;
        if ($temp !== null) {
            $score -= match (true) {
                $temp >= ($thresholds['temperature_critical'] ?? 70) => 25,
                $temp >= ($thresholds['temperature_warning'] ?? 55) => 12,
                default => 0,
            };
        }

        $fan = strtolower((string) ($metrics['fan_status'] ?? ''));
        if (in_array($fan, ['fail', 'failed', 'alarm', 'critical', 'down'], true)) {
            $score -= 20;
        }

        $psu = strtolower((string) ($metrics['power_supply_status'] ?? ''));
        if (in_array($psu, ['fail', 'failed', 'alarm', 'critical', 'down'], true)) {
            $score -= 25;
        }

        return (int) max(0, min(100, $score));
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function inferFanStatus(array $metrics): string
    {
        $cpu = $metrics['cpu_percent'] ?? null;
        $temp = $metrics['temperature_c'] ?? null;

        if ($temp !== null && $temp >= (config('olt_health.thresholds.temperature_critical') ?? 70)) {
            return 'check_required';
        }

        if ($cpu !== null && $cpu >= (config('olt_health.thresholds.cpu_critical') ?? 90)) {
            return 'check_required';
        }

        return 'ok';
    }
}
