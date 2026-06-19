<?php

namespace App\Services\Olt;

use App\Models\Device;
use App\Models\FiberFaultLog;
use App\Models\SignalAlert;
use App\Support\TenantResolver;
use Illuminate\Support\Collection;

final class OltAlarmCenterService
{
    /**
     * @return array{
     *     summary: array<string, int>,
     *     alarms: list<array<string, mixed>>
     * }
     */
    public function snapshot(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        $alarms = collect()
            ->merge($this->signalAlarms($tenantId))
            ->merge($this->fiberFaultAlarms($tenantId))
            ->merge($this->oltHealthAlarms($tenantId))
            ->sortByDesc('detected_at')
            ->values()
            ->take(100)
            ->all();

        $summary = [
            'total' => count($alarms),
            'critical' => collect($alarms)->where('severity', 'critical')->count(),
            'warning' => collect($alarms)->where('severity', 'warning')->count(),
            'pon_down' => collect($alarms)->where('type', 'pon_outage')->count(),
            'fiber_cut' => collect($alarms)->where('type', 'fiber_fault')->count(),
            'temperature' => collect($alarms)->where('type', 'high_temperature')->count(),
        ];

        return compact('summary', 'alarms');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function signalAlarms(int $tenantId): Collection
    {
        return SignalAlert::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('resolved_at')
            ->with(['olt:id,display_name', 'device:id,display_name'])
            ->orderByDesc('created_at')
            ->limit(40)
            ->get()
            ->map(fn (SignalAlert $a) => [
                'id' => 'signal-'.$a->id,
                'type' => $this->mapAlertType($a->alert_type ?? 'signal'),
                'severity' => $a->severity ?? 'warning',
                'title' => $a->title ?? $a->alert_type ?? 'Signal alert',
                'description' => $a->message,
                'olt' => $a->olt?->display_name ?? $a->device?->display_name,
                'detected_at' => ($a->detected_at ?? $a->created_at)?->toIso8601String(),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fiberFaultAlarms(int $tenantId): Collection
    {
        return FiberFaultLog::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('resolved_at')
            ->orderByDesc('detected_at')
            ->limit(20)
            ->get()
            ->map(fn (FiberFaultLog $f) => [
                'id' => 'fault-'.$f->id,
                'type' => 'fiber_fault',
                'severity' => $f->severity ?? 'critical',
                'title' => $f->fault_type ?: 'Fiber cut / mass offline',
                'description' => $f->description,
                'affected' => (int) ($f->affected_customer_count ?? 0),
                'detected_at' => $f->detected_at?->toIso8601String(),
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function oltHealthAlarms(int $tenantId): Collection
    {
        return Device::query()
            ->where('tenant_id', $tenantId)
            ->where('type', 'olt')
            ->where('status', 'active')
            ->get()
            ->flatMap(function (Device $olt): array {
                $health = is_array($olt->olt_health) ? $olt->olt_health : [];
                $items = [];
                $cpu = $health['cpu_percent'] ?? null;
                $temp = $health['temperature_c'] ?? null;
                $fan = $health['fan_status'] ?? null;

                if ($cpu !== null && (int) $cpu >= 85) {
                    $items[] = [
                        'id' => 'olt-cpu-'.$olt->id,
                        'type' => 'high_cpu',
                        'severity' => 'warning',
                        'title' => 'High CPU — '.$olt->adminLabel(),
                        'description' => "CPU at {$cpu}%",
                        'olt' => $olt->adminLabel(),
                        'detected_at' => now()->toIso8601String(),
                    ];
                }

                if ($temp !== null && (float) $temp >= 65) {
                    $items[] = [
                        'id' => 'olt-temp-'.$olt->id,
                        'type' => 'high_temperature',
                        'severity' => 'critical',
                        'title' => 'High temperature — '.$olt->adminLabel(),
                        'description' => "Module temp {$temp} °C",
                        'olt' => $olt->adminLabel(),
                        'detected_at' => now()->toIso8601String(),
                    ];
                }

                if ($fan === 'failed' || $fan === 'critical') {
                    $items[] = [
                        'id' => 'olt-fan-'.$olt->id,
                        'type' => 'fan_failure',
                        'severity' => 'critical',
                        'title' => 'Fan failure — '.$olt->adminLabel(),
                        'description' => 'Fan status: '.$fan,
                        'olt' => $olt->adminLabel(),
                        'detected_at' => now()->toIso8601String(),
                    ];
                }

                return $items;
            });
    }

    private function mapAlertType(string $type): string
    {
        return match (true) {
            str_contains(strtolower($type), 'los') => 'los_burst',
            str_contains(strtolower($type), 'pon') => 'pon_outage',
            str_contains(strtolower($type), 'dying') => 'dying_gasp',
            default => 'onu_signal',
        };
    }
}
