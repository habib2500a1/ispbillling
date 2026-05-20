<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasRoleDashboard;
use App\Filament\Widgets\BandwidthLiveChartWidget;
use App\Filament\Widgets\BandwidthOnlineSessionsWidget;
use App\Services\Dashboard\DashboardMetricsService;
use Filament\Pages\Page;

class MikrotikDashboard extends Page
{
    use HasRoleDashboard;

    protected static ?string $navigationIcon = 'heroicon-o-server';

    protected static string $view = 'filament.pages.mikrotik-dashboard';

    protected static ?string $navigationLabel = 'MikroTik dashboard';

    protected static ?string $title = 'MikroTik dashboard';

    protected static ?string $navigationGroup = 'Overview';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return static::staff()->canMikrotik();
    }

    /**
     * @return list<array{label: string, value: string, hint: string, class?: string}>
     */
    public function getStatCards(): array
    {
        $m = app(DashboardMetricsService::class)->mikrotikSnapshot();

        return [
            ['label' => 'PPPoE online', 'value' => (string) ($m['online_now'] ?? 0), 'hint' => 'Active sessions', 'class' => 'isp-hub-stat--slate'],
            ['label' => 'Routers online', 'value' => ($m['mikrotik_online'] ?? 0).'/'.($m['mikrotik_total'] ?? 0), 'hint' => 'API reachable'],
            ['label' => 'Download (live)', 'value' => ($m['bandwidth_mbps'] ?? 0).' Mbps/s', 'hint' => 'Aggregate sessions'],
            ['label' => 'Active customers', 'value' => (string) ($m['active_subscribers'] ?? $m['active'] ?? '—'), 'hint' => 'Billing active'],
        ];
    }

    /**
     * @return array<class-string>
     */
    public function getFooterWidgets(): array
    {
        return [
            BandwidthOnlineSessionsWidget::class,
            BandwidthLiveChartWidget::class,
        ];
    }
}
