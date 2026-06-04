<?php

namespace App\Services\Olt;

use App\Models\Device;
use App\Models\OltPort;
use App\Models\PonSignalStat;
use App\Services\Optical\OpticalDashboardService;
use Illuminate\Support\Facades\Schema;

/**
 * Enterprise GPON NOC KPIs for OLT hub and optical command center.
 */
final class GponNocHubService
{
    public function __construct(
        private readonly OpticalDashboardService $optical,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(int $tenantId): array
    {
        $optical = Schema::hasTable('onu_signal_logs')
            ? $this->optical->snapshot($tenantId)
            : [
                'total_onus' => 0,
                'online_onus' => 0,
                'offline_onus' => 0,
                'open_alerts' => 0,
                'fiber_faults' => 0,
                'critical_onus' => 0,
                'warning_onus' => 0,
                'avg_rx_dbm' => null,
            ];

        $oltQuery = Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'olt')
            ->where('status', '!=', 'decommissioned');

        $ponPorts = OltPort::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->count();

        $topPon = PonSignalStat::query()
            ->where('tenant_id', $tenantId)
            ->where('onu_total', '>', 0)
            ->latestPerPort($tenantId)
            ->orderByDesc('fault_percent')
            ->orderByDesc('onu_total')
            ->limit(5)
            ->with([
                'olt:id,display_name',
                'oltPort:id,device_id,card_index,pon_index,label,meta',
            ])
            ->get();

        $vendors = (clone $oltQuery)
            ->selectRaw('COALESCE(olt_driver, vendor, ?) as label, COUNT(*) as cnt', ['unknown'])
            ->groupBy('label')
            ->orderByDesc('cnt')
            ->limit(8)
            ->pluck('cnt', 'label')
            ->all();

        return [
            'olts' => (clone $oltQuery)->count(),
            'olts_active' => (clone $oltQuery)->where('status', 'active')->count(),
            'pon_ports' => $ponPorts,
            'onus' => (int) ($optical['total_onus'] ?? 0),
            'onus_online' => (int) ($optical['online_onus'] ?? 0),
            'onus_offline' => (int) ($optical['offline_onus'] ?? 0),
            'active_alarms' => (int) ($optical['open_alerts'] ?? 0),
            'fiber_faults' => (int) ($optical['fiber_faults'] ?? 0),
            'critical_onus' => (int) ($optical['critical_onus'] ?? 0),
            'warning_onus' => (int) ($optical['warning_onus'] ?? 0),
            'avg_rx_dbm' => $optical['avg_rx_dbm'] ?? null,
            'top_pon_ports' => $topPon
                ->map(fn (PonSignalStat $p): array => \App\Services\Optical\PonPortNetworkSummary::toRow($p))
                ->values()
                ->all(),
            'vendors' => $vendors,
        ];
    }
}
