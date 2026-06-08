<?php

namespace App\Services\IspOs;

use App\Models\Device;
use App\Models\FiberFaultLog;
use App\Models\MikrotikServer;
use App\Models\SignalAlert;
use App\Support\TenantResolver;

final class FaultManagementService
{
    /**
     * @return array{summary: array<string, int>, faults: list<array<string, mixed>>}
     */
    public function payload(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $faults = array_merge(
            $this->fiberFaults($tenantId),
            $this->signalAlerts($tenantId),
            $this->deviceOfflineAlerts($tenantId),
        );

        usort($faults, fn (array $a, array $b): int => ($b['sort'] ?? 0) <=> ($a['sort'] ?? 0));

        $summary = [
            'active' => count($faults),
            'critical' => count(array_filter($faults, fn (array $f): bool => ($f['severity'] ?? '') === 'critical')),
            'warning' => count(array_filter($faults, fn (array $f): bool => ($f['severity'] ?? '') === 'warning')),
            'offline_devices' => count(array_filter($faults, fn (array $f): bool => str_contains((string) ($f['type'] ?? ''), 'offline'))),
            'signal_alerts' => count(array_filter($faults, fn (array $f): bool => ($f['type'] ?? '') === 'signal')),
            'fiber_alerts' => count(array_filter($faults, fn (array $f): bool => ($f['type'] ?? '') === 'fiber')),
        ];

        return [
            'summary' => $summary,
            'faults' => array_slice($faults, 0, 50),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fiberFaults(int $tenantId): array
    {
        return FiberFaultLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('resolved_at')
            ->with('olt:id,display_name,label')
            ->orderByDesc('detected_at')
            ->limit(20)
            ->get()
            ->map(fn (FiberFaultLog $f): array => [
                'id' => 'fiber-'.$f->id,
                'type' => 'fiber',
                'severity' => $f->severity ?? 'critical',
                'title' => $f->fault_type ?? 'Fiber fault',
                'message' => $f->description,
                'entity' => $f->olt?->display_name ?? $f->olt?->label ?? 'OLT',
                'affected' => (int) $f->affected_onu_count,
                'zones' => $f->affected_zones ?? [],
                'at' => $f->detected_at?->diffForHumans(),
                'sort' => $f->detected_at?->timestamp ?? 0,
                'url' => \App\Filament\Pages\OpticalMonitoringHub::getUrl(['tab' => 'alerts']),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function signalAlerts(int $tenantId): array
    {
        return SignalAlert::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('resolved_at')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (SignalAlert $a): array => [
                'id' => 'signal-'.$a->id,
                'type' => 'signal',
                'severity' => $a->severity ?? 'warning',
                'title' => $a->title ?? 'Signal alert',
                'message' => $a->message,
                'entity' => 'OLT #'.($a->olt_id ?? '—'),
                'affected' => 0,
                'zones' => [],
                'at' => $a->created_at?->diffForHumans(),
                'sort' => $a->created_at?->timestamp ?? 0,
                'url' => \App\Filament\Pages\OpticalMonitoringHub::getUrl(['tab' => 'alerts']),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function deviceOfflineAlerts(int $tenantId): array
    {
        $out = [];
        $offlineRouters = MikrotikServer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('last_api_status', 'offline')
            ->limit(10)
            ->get(['id', 'name', 'last_checked_at']);

        foreach ($offlineRouters as $router) {
            $out[] = [
                'id' => 'router-'.$router->id,
                'type' => 'router_offline',
                'severity' => 'critical',
                'title' => 'Router offline',
                'message' => 'MikroTik '.$router->name.' is unreachable.',
                'entity' => $router->name,
                'affected' => 0,
                'zones' => [],
                'at' => $router->last_checked_at?->diffForHumans() ?? '—',
                'sort' => $router->last_checked_at?->timestamp ?? 0,
                'url' => \App\Filament\Resources\MikrotikServerResource::getUrl('edit', ['record' => $router->id]),
            ];
        }

        $offlineOlts = Device::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'olt')
            ->where('status', 'offline')
            ->limit(10)
            ->get(['id', 'display_name', 'label', 'updated_at']);

        foreach ($offlineOlts as $olt) {
            $out[] = [
                'id' => 'olt-'.$olt->id,
                'type' => 'olt_offline',
                'severity' => 'critical',
                'title' => 'OLT offline',
                'message' => ($olt->display_name ?? $olt->label ?? 'OLT').' reported offline.',
                'entity' => $olt->display_name ?? $olt->label ?? 'OLT',
                'affected' => 0,
                'zones' => [],
                'at' => $olt->updated_at?->diffForHumans() ?? '—',
                'sort' => $olt->updated_at?->timestamp ?? 0,
                'url' => \App\Filament\Resources\OltResource::getUrl('edit', ['record' => $olt->id]),
            ];
        }

        return $out;
    }
}
