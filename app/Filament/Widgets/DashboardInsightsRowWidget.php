<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\ChecksDashboardWidgetAccess;
use App\Filament\Concerns\HasDashboardLazySkeleton;
use App\Filament\Pages\BandwidthMonitor;
use App\Filament\Pages\MikrotikDashboard;
use App\Services\Dashboard\DashboardMetricsService;
use App\Support\Rbac\StaffCapability;
use Filament\Widgets\Widget;

/**
 * Full-width analytics row: revenue trend + online subscribers (replaces separate chart widgets).
 */
class DashboardInsightsRowWidget extends Widget
{
    use ChecksDashboardWidgetAccess;
    use HasDashboardLazySkeleton;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected static string $view = 'filament.widgets.dashboard-insights-row';

    protected int|string|array $columnSpan = 'full';

    protected function dashboardSkeletonVariant(): string
    {
        return 'insights';
    }

    protected function dashboardSkeletonHeight(): ?string
    {
        return '14rem';
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = auth()->user();
        $capability = StaffCapability::for($user);
        $metrics = app(DashboardMetricsService::class);
        $snap = $metrics->snapshot();

        $showRevenue = $capability->canSeeRevenueChart();
        $showOnline = $capability->canSeeOnlineChart();
        $showNetwork = $capability->canNetwork();
        $showPackages = $capability->canCustomers();
        $showGrowth = $capability->canCustomers();

        $revenue = $showRevenue ? $metrics->revenueTrend(14) : null;
        $online = $showOnline ? $metrics->onlineUsersTrend(24) : null;
        $network = $showNetwork ? $metrics->networkOverview() : null;
        $packages = $showPackages ? $metrics->packageDistribution() : null;
        $growth = $showGrowth ? $metrics->subscriberGrowth(14) : null;

        $collected = $revenue['collected'] ?? [];
        $invoiced = $revenue['invoiced'] ?? [];
        $onlineSeries = $online['online'] ?? [];
        $growthValues = $growth['values'] ?? [];

        $bandwidthTrend = $network['bandwidth_trend'] ?? ['labels' => [], 'download_mbps' => []];
        $bwValues = $bandwidthTrend['download_mbps'] ?? [];

        return [
            'show_revenue' => $showRevenue,
            'show_online' => $showOnline,
            'show_network' => $showNetwork,
            'show_packages' => $showPackages,
            'show_growth' => $showGrowth,
            'revenue' => $revenue,
            'online' => $online,
            'network' => $network,
            'packages' => $packages,
            'growth' => $growth,
            'collected_sum' => array_sum($collected),
            'invoiced_sum' => array_sum($invoiced),
            'collected_today' => (float) ($snap['collected_today'] ?? 0),
            'online_now' => (int) ($snap['online_now'] ?? 0),
            'online_peak' => $onlineSeries !== [] ? (int) max($onlineSeries) : 0,
            'growth_total' => array_sum($growthValues),
            'growth_peak' => $growthValues !== [] ? (int) max($growthValues) : 0,
            'bandwidth_max' => max(1, max($bwValues ?: [0])),
            'package_max' => max(1, max($packages['values'] ?? [0])),
            'growth_max' => max(1, max($growthValues ?: [0])),
            'bandwidth_url' => BandwidthMonitor::canAccess() ? BandwidthMonitor::getUrl() : null,
            'routers_url' => MikrotikDashboard::canAccess() ? MikrotikDashboard::getUrl() : null,
        ];
    }
}
