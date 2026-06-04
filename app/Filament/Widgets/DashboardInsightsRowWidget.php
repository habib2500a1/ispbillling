<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\ChecksDashboardWidgetAccess;
use App\Services\Dashboard\DashboardMetricsService;
use App\Support\Rbac\StaffCapability;
use Filament\Widgets\Widget;

/**
 * Full-width analytics row: revenue trend + online subscribers (replaces separate chart widgets).
 */
class DashboardInsightsRowWidget extends Widget
{
    use ChecksDashboardWidgetAccess;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected static string $view = 'filament.widgets.dashboard-insights-row';

    protected int|string|array $columnSpan = 'full';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = auth()->user();
        $capability = StaffCapability::for($user);
        $metrics = app(DashboardMetricsService::class);
        $snap = $metrics->snapshot();

        $showRevenue = $capability->canSeeRevenueChart();
        $showOnline = $capability->canSeeOnlineChart();

        $revenue = $showRevenue ? $metrics->revenueTrend(14) : null;
        $online = $showOnline ? $metrics->onlineUsersTrend(24) : null;

        $collected = $revenue['collected'] ?? [];
        $invoiced = $revenue['invoiced'] ?? [];
        $onlineSeries = $online['online'] ?? [];

        return [
            'show_revenue' => $showRevenue,
            'show_online' => $showOnline,
            'revenue' => $revenue,
            'online' => $online,
            'collected_sum' => array_sum($collected),
            'invoiced_sum' => array_sum($invoiced),
            'collected_today' => (float) ($snap['collected_today'] ?? 0),
            'online_now' => (int) ($snap['online_now'] ?? 0),
            'online_peak' => $onlineSeries !== [] ? (int) max($onlineSeries) : 0,
        ];
    }
}
