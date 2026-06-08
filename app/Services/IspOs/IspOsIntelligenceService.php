<?php

namespace App\Services\IspOs;

use App\Models\Customer;
use App\Models\Device;
use App\Models\FiberFaultLog;
use App\Models\MikrotikServer;
use App\Models\SignalAlert;
use App\Services\Dashboard\DashboardMetricsService;
use App\Support\CustomerStatus;
use App\Support\SafeCache;
use App\Support\TenantResolver;

final class IspOsIntelligenceService
{
    public function __construct(
        private readonly DashboardMetricsService $dashboard,
        private readonly OperationalInsightsService $insights,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        return SafeCache::remember(
            'isp_os:intelligence:'.$tenantId,
            now()->addSeconds(60),
            fn (): array => $this->build($tenantId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function build(int $tenantId): array
    {
        $snap = $this->dashboard->snapshot($tenantId);
        $customers = $this->customerOnlineStats($tenantId);
        $routers = $this->routerStats($tenantId);
        $olts = $this->oltStats($tenantId);
        $onus = $this->onuStats($tenantId);
        $faults = $this->faultStats($tenantId);
        $health = $this->computeHealthScore($customers, $routers, $olts, $onus, $faults, $snap);

        return [
            'customers_total' => $customers['total'],
            'customers_online' => $customers['online'],
            'customers_offline' => $customers['offline'],
            'routers_total' => $routers['total'],
            'routers_online' => $routers['online'],
            'olts_total' => $olts['total'],
            'olts_online' => $olts['online'],
            'onus_active' => $onus['online'],
            'onus_offline' => $onus['offline'],
            'open_tickets' => (int) ($snap['open_tickets'] ?? 0),
            'active_faults' => $faults['active'],
            'critical_faults' => $faults['critical'],
            'network_health_score' => $health,
            'revenue_today' => (float) ($snap['collected_today'] ?? 0),
            'insights' => $this->insights->forTenant($tenantId),
        ];
    }

    /**
     * @return array{total: int, online: int, offline: int}
     */
    private function customerOnlineStats(int $tenantId): array
    {
        $row = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', CustomerStatus::ACTIVE)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_ppp_online IS TRUE THEN 1 ELSE 0 END) as online')
            ->first();

        $total = (int) ($row->total ?? 0);
        $online = (int) ($row->online ?? 0);

        return [
            'total' => $total,
            'online' => $online,
            'offline' => max(0, $total - $online),
        ];
    }

    /**
     * @return array{total: int, online: int, offline: int}
     */
    private function routerStats(int $tenantId): array
    {
        $base = MikrotikServer::withoutGlobalScopes()->where('tenant_id', $tenantId);
        $total = (int) (clone $base)->count();
        $online = (int) (clone $base)->where('last_api_status', 'online')->count();

        return [
            'total' => $total,
            'online' => $online,
            'offline' => max(0, $total - $online),
        ];
    }

    /**
     * @return array{total: int, online: int}
     */
    private function oltStats(int $tenantId): array
    {
        $base = Device::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('type', 'olt');
        $total = (int) (clone $base)->count();
        $online = (int) (clone $base)->where('status', 'active')->count();

        return ['total' => $total, 'online' => $online];
    }

    /**
     * @return array{total: int, online: int, offline: int}
     */
    private function onuStats(int $tenantId): array
    {
        $base = Device::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('type', 'onu');
        $total = (int) (clone $base)->count();
        $online = (int) (clone $base)->whereIn('onu_oper_status', ['online', 'active', 'up'])->count();

        return [
            'total' => $total,
            'online' => $online,
            'offline' => max(0, $total - $online),
        ];
    }

    /**
     * @return array{active: int, critical: int}
     */
    private function faultStats(int $tenantId): array
    {
        $fiberActive = FiberFaultLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('resolved_at')
            ->count();
        $signalActive = SignalAlert::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('resolved_at')
            ->count();
        $critical = FiberFaultLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('resolved_at')
            ->where('severity', 'critical')
            ->count()
            + SignalAlert::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNull('resolved_at')
                ->where('severity', 'critical')
                ->count();

        return [
            'active' => $fiberActive + $signalActive,
            'critical' => $critical,
        ];
    }

    /**
     * @param  array{total: int, online: int, offline: int}  $customers
     * @param  array{total: int, online: int, offline: int}  $routers
     * @param  array{total: int, online: int}  $olts
     * @param  array{total: int, online: int, offline: int}  $onus
     * @param  array{active: int, critical: int}  $faults
     * @param  array<string, mixed>  $snap
     */
    private function computeHealthScore(
        array $customers,
        array $routers,
        array $olts,
        array $onus,
        array $faults,
        array $snap,
    ): int {
        $routerPct = $routers['total'] > 0 ? $routers['online'] / $routers['total'] : 1;
        $oltPct = $olts['total'] > 0 ? $olts['online'] / $olts['total'] : 1;
        $onuPct = $onus['total'] > 0 ? $onus['online'] / $onus['total'] : 1;
        $customerPct = $customers['total'] > 0 ? $customers['online'] / $customers['total'] : 1;

        $openTickets = (int) ($snap['open_tickets'] ?? 0);
        $ticketPenalty = min(15, $openTickets * 0.5);
        $faultPenalty = min(20, $faults['critical'] * 4 + max(0, $faults['active'] - $faults['critical']) * 1.5);

        $raw = (
            $routerPct * 20
            + $oltPct * 20
            + $onuPct * 25
            + $customerPct * 25
            - $ticketPenalty
            - $faultPenalty
        );

        return (int) max(0, min(100, round($raw)));
    }

    /**
     * @return list<array{label: string, value: string, hint?: string, tone: string, url?: string}>
     */
    public function kpiCards(?int $tenantId = null): array
    {
        $p = $this->payload($tenantId);

        return [
            ['label' => 'Total customers', 'value' => number_format($p['customers_total']), 'hint' => number_format($p['customers_online']).' online', 'tone' => 'cyan', 'url' => \App\Filament\Resources\CustomerResource::getUrl()],
            ['label' => 'Online customers', 'value' => number_format($p['customers_online']), 'tone' => 'emerald', 'url' => \App\Filament\Pages\OnlineClientsMonitoring::getUrl()],
            ['label' => 'Offline customers', 'value' => number_format($p['customers_offline']), 'tone' => 'rose', 'url' => \App\Filament\Pages\OnlineClientsMonitoring::getUrl()],
            ['label' => 'Routers online', 'value' => number_format($p['routers_online']).'/'.number_format($p['routers_total']), 'tone' => 'indigo', 'url' => \App\Filament\Resources\MikrotikServerResource::getUrl()],
            ['label' => 'OLTs active', 'value' => number_format($p['olts_online']).'/'.number_format($p['olts_total']), 'tone' => 'violet', 'url' => \App\Filament\Pages\OltHub::getUrl()],
            ['label' => 'ONUs active', 'value' => number_format($p['onus_active']), 'hint' => number_format($p['onus_offline']).' offline', 'tone' => 'sky', 'url' => \App\Filament\Pages\OpticalMonitoringHub::getUrl()],
            ['label' => 'Open tickets', 'value' => number_format($p['open_tickets']), 'tone' => 'amber', 'url' => \App\Filament\Pages\SupportHub::getUrl()],
            ['label' => 'Active faults', 'value' => number_format($p['active_faults']), 'hint' => number_format($p['critical_faults']).' critical', 'tone' => 'rose', 'url' => \App\Filament\Pages\FaultManagementHub::getUrl()],
            ['label' => 'Network health', 'value' => $p['network_health_score'].'%', 'tone' => $p['network_health_score'] >= 80 ? 'emerald' : ($p['network_health_score'] >= 60 ? 'amber' : 'rose')],
        ];
    }
}
