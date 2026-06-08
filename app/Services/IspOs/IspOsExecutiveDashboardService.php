<?php

namespace App\Services\IspOs;

use App\Filament\Pages\AccountingHub;
use App\Filament\Pages\AiOperationsCopilotHub;
use App\Filament\Pages\BillingOverview;
use App\Filament\Pages\ClientsHub;
use App\Filament\Pages\FieldTechnicianCenter;
use App\Filament\Pages\HrPayrollHub;
use App\Filament\Pages\InventoryHub;
use App\Filament\Pages\NotificationsHub;
use App\Filament\Pages\OperationsHub;
use App\Filament\Pages\ReportsHub;
use App\Filament\Pages\ResellersHub;
use App\Services\Finance\FinanceHubDashboardService;
use App\Services\Hr\WorkforceHubDashboardService;
use App\Support\PerformanceSettings;
use App\Support\SafeCache;
use App\Support\TenantResolver;

/**
 * Unified ISP OS executive layer — read-only composition of existing services.
 */
final class IspOsExecutiveDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::currentTenantId() ?? 1;
        $cacheKey = 'isp_os:executive:'.$tenantId;

        return SafeCache::remember($cacheKey, PerformanceSettings::hubCacheSeconds(), fn () => $this->build($tenantId));
    }

    /**
     * @return array<string, mixed>
     */
    private function build(int $tenantId): array
    {
        $intel = app(IspOsIntelligenceService::class)->payload($tenantId);
        $finance = app(FinanceHubDashboardService::class)->snapshot();
        $workforce = app(WorkforceHubDashboardService::class)->snapshot($tenantId);
        $fKpis = $finance['kpis'] ?? [];
        $wKpis = $workforce['kpis'] ?? [];

        return [
            'intelligence' => $intel,
            'network_kpis' => app(IspOsIntelligenceService::class)->kpiCards($tenantId),
            'executive_kpis' => [
                ['key' => 'revenue_today', 'label' => "Today's collection", 'value' => (float) ($fKpis['today_collection'] ?? $intel['revenue_today'] ?? 0), 'format' => 'money'],
                ['key' => 'monthly_collection', 'label' => 'Monthly collection', 'value' => (float) ($fKpis['monthly_collection'] ?? 0), 'format' => 'money'],
                ['key' => 'due_collection', 'label' => 'Due outstanding', 'value' => (float) ($fKpis['due_collection'] ?? 0), 'format' => 'money'],
                ['key' => 'customers_online', 'label' => 'Customers online', 'value' => (int) ($intel['customers_online'] ?? 0), 'format' => 'number'],
                ['key' => 'open_tickets', 'label' => 'Open tickets', 'value' => (int) ($intel['open_tickets'] ?? 0), 'format' => 'number'],
                ['key' => 'active_faults', 'label' => 'Active faults', 'value' => (int) ($intel['active_faults'] ?? 0), 'format' => 'number'],
                ['key' => 'present_today', 'label' => 'Staff present', 'value' => (int) ($wKpis['present_today'] ?? 0), 'format' => 'number'],
                ['key' => 'network_health', 'label' => 'Network health', 'value' => (int) ($intel['network_health_score'] ?? 0), 'format' => 'percent'],
            ],
            'command_centers' => $this->commandCenters($fKpis, $wKpis, $intel),
            'operations_modules' => $this->operationsModules(),
            'refreshed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $fKpis
     * @param  array<string, mixed>  $wKpis
     * @param  array<string, mixed>  $intel
     * @return list<array<string, mixed>>
     */
    private function commandCenters(array $fKpis, array $wKpis, array $intel): array
    {
        return [
            [
                'title' => 'Finance Operations',
                'desc' => 'Revenue · GL · collections · P&L',
                'url' => AccountingHub::getUrl(),
                'icon' => 'calculator',
                'tone' => 'emerald',
                'badge' => number_format($fKpis['monthly_collection'] ?? 0, 0).' BDT',
            ],
            [
                'title' => 'Workforce Operations',
                'desc' => 'HR · attendance · payroll · tasks',
                'url' => HrPayrollHub::getUrl(),
                'icon' => 'user-group',
                'tone' => 'rose',
                'badge' => ($wKpis['present_today'] ?? 0).' present',
            ],
            [
                'title' => 'Communication Hub',
                'desc' => 'SMS · WhatsApp · email · campaigns',
                'url' => NotificationsHub::getUrl(),
                'icon' => 'bell-alert',
                'tone' => 'sky',
                'badge' => 'Live',
            ],
            [
                'title' => 'Inventory & Assets',
                'desc' => 'Stock · devices · POS · vendors',
                'url' => InventoryHub::getUrl(),
                'icon' => 'cube',
                'tone' => 'orange',
                'badge' => 'Assets',
            ],
            [
                'title' => 'Billing Center',
                'desc' => 'Invoices · due · analytics',
                'url' => BillingOverview::getUrl(),
                'icon' => 'banknotes',
                'tone' => 'violet',
                'badge' => number_format($fKpis['due_collection'] ?? 0, 0).' due',
            ],
            [
                'title' => 'CRM / Clients',
                'desc' => 'Subscribers · lists · lifecycle',
                'url' => ClientsHub::getUrl(),
                'icon' => 'users',
                'tone' => 'cyan',
                'badge' => number_format($intel['customers_total'] ?? 0).' subs',
            ],
            [
                'title' => 'Reports Intelligence',
                'desc' => 'Analytics · regulatory · exports',
                'url' => ReportsHub::getUrl(),
                'icon' => 'chart-pie',
                'tone' => 'indigo',
                'badge' => 'Reports',
            ],
            [
                'title' => 'Reseller Center',
                'desc' => 'Partners · commission · wallet',
                'url' => ResellersHub::getUrl(),
                'icon' => 'building-storefront',
                'tone' => 'purple',
                'badge' => 'Partners',
            ],
            [
                'title' => 'AI Operations',
                'desc' => 'Copilot · insights · ask',
                'url' => AiOperationsCopilotHub::getUrl(),
                'icon' => 'sparkles',
                'tone' => 'fuchsia',
                'badge' => 'AI',
            ],
            [
                'title' => 'Module Directory',
                'desc' => 'All 80+ admin modules',
                'url' => OperationsHub::getUrl(),
                'icon' => 'squares-plus',
                'tone' => 'slate',
                'badge' => 'Index',
            ],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function operationsModules(): array
    {
        return [
            ['title' => 'Network center', 'desc' => 'MikroTik, SNMP, bandwidth', 'url' => \App\Filament\Pages\NetworkIntelligenceHub::getUrl(), 'icon' => 'cpu-chip', 'tone' => 'cyan'],
            ['title' => 'OLT operations', 'desc' => 'GPON, PON, optical NOC', 'url' => \App\Filament\Pages\OltHub::getUrl(), 'icon' => 'server-stack', 'tone' => 'indigo'],
            ['title' => 'Fiber GIS map', 'desc' => 'Routes, splitters, faults', 'url' => \App\Filament\Pages\FiberPlantMap::getUrl(), 'icon' => 'map', 'tone' => 'teal'],
            ['title' => 'Fault management', 'desc' => 'Active faults, RCA', 'url' => \App\Filament\Pages\FaultManagementHub::getUrl(), 'icon' => 'exclamation-triangle', 'tone' => 'rose'],
            ['title' => 'Field operations', 'desc' => 'Technicians, visits, mobile', 'url' => FieldTechnicianCenter::getUrl(), 'icon' => 'wrench-screwdriver', 'tone' => 'amber'],
            ['title' => 'Support & tickets', 'desc' => 'SLA, inbox, field visits', 'url' => \App\Filament\Pages\SupportHub::getUrl(), 'icon' => 'lifebuoy', 'tone' => 'sky'],
            ['title' => 'NOC wall', 'desc' => '24/7 large-screen monitor', 'url' => \App\Filament\Pages\NocWall::getUrl(), 'icon' => 'tv', 'tone' => 'slate'],
        ];
    }
}
