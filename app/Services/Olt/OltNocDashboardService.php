<?php

namespace App\Services\Olt;

use App\Models\Device;
use Illuminate\Support\Collection;

final class OltNocDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(int $tenantId): array
    {
        $olts = Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'olt')
            ->where('status', '!=', 'decommissioned')
            ->withCount([
                'onus as onus_total',
                'onus as onus_online' => fn ($q) => $q->whereIn('onu_oper_status', ['online', 'active', 'up']),
            ])
            ->orderBy('display_name')
            ->get();

        $thresholds = config('olt_health.thresholds', []);
        $cpuWarn = (int) ($thresholds['cpu_warning'] ?? 75);
        $memWarn = (int) ($thresholds['memory_warning'] ?? 80);

        $highCpu = 0;
        $highMem = 0;
        $unhealthy = 0;
        $offline = 0;

        $rows = $olts->map(function (Device $olt) use ($cpuWarn, $memWarn, &$highCpu, &$highMem, &$unhealthy, &$offline): array {
            $health = is_array($olt->olt_health) ? $olt->olt_health : [];
            $cpu = isset($health['cpu_percent']) ? (int) $health['cpu_percent'] : null;
            $mem = isset($health['memory_percent']) ? (int) $health['memory_percent'] : null;
            $temp = isset($health['temperature_c']) ? (float) $health['temperature_c'] : null;
            $score = isset($health['health_score']) ? (int) $health['health_score'] : null;
            $snmpOk = (bool) ($health['snmp_ok'] ?? $health['health_snmp_ok'] ?? false);

            if ($olt->status === 'offline') {
                $offline++;
            }
            if ($cpu !== null && $cpu >= $cpuWarn) {
                $highCpu++;
            }
            if ($mem !== null && $mem >= $memWarn) {
                $highMem++;
            }
            if ($score !== null && $score < 60) {
                $unhealthy++;
            }

            return [
                'id' => $olt->id,
                'name' => $olt->adminLabel(),
                'management_ip' => $olt->management_ip,
                'vendor' => $olt->vendor,
                'driver' => $olt->olt_driver,
                'status' => $olt->status,
                'cpu_percent' => $cpu,
                'memory_percent' => $mem,
                'temperature_c' => $temp,
                'fan_status' => $health['fan_status'] ?? null,
                'power_supply_status' => $health['power_supply_status'] ?? null,
                'health_score' => $score,
                'health_label' => $this->healthLabel($score),
                'snmp_ok' => $snmpOk,
                'onus_online' => (int) ($health['onus_online'] ?? $olt->onus_online ?? 0),
                'onus_offline' => (int) ($health['onus_offline'] ?? max(0, $olt->onus_total - ($olt->onus_online ?? 0))),
                'onus_total' => (int) $olt->onus_total,
                'interfaces_up' => $health['interfaces_up'] ?? null,
                'interfaces_total' => $health['interfaces_total'] ?? null,
                'pon_ports' => $olt->ports()->count(),
                'uptime_ticks' => $health['sys_uptime_ticks'] ?? null,
                'uptime_human' => $this->formatUptime($health['sys_uptime_ticks'] ?? null),
                'last_polled_at' => $olt->last_health_polled_at?->toIso8601String()
                    ?? $olt->last_snmp_poll_at?->toIso8601String(),
            ];
        });

        return [
            'olt_total' => $olts->count(),
            'olt_online' => $olts->where('status', 'active')->count(),
            'olt_offline' => $offline,
            'olt_high_cpu' => $highCpu,
            'olt_high_memory' => $highMem,
            'olt_unhealthy' => $unhealthy,
            'avg_health_score' => $this->averageHealth($rows),
            'olts' => $rows->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function averageHealth(Collection $rows): ?int
    {
        $scores = $rows->pluck('health_score')->filter(fn ($s) => $s !== null);
        if ($scores->isEmpty()) {
            return null;
        }

        return (int) round($scores->avg());
    }

    private function healthLabel(?int $score): string
    {
        if ($score === null) {
            return 'Unknown';
        }

        return match (true) {
            $score >= 85 => 'Healthy',
            $score >= 70 => 'Good',
            $score >= 50 => 'Degraded',
            default => 'Critical',
        };
    }

    private function formatUptime(mixed $ticks): ?string
    {
        if (! is_numeric($ticks) || (int) $ticks <= 0) {
            return null;
        }

        $seconds = (int) floor(((int) $ticks) / 100);

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);

        if ($days > 0) {
            return "{$days}d {$hours}h";
        }

        $mins = intdiv($seconds % 3600, 60);

        return "{$hours}h {$mins}m";
    }
}
