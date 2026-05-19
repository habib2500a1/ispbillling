<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BandwidthLiveChartWidget;
use App\Filament\Widgets\DashboardCommandStripWidget;
use App\Filament\Widgets\DashboardHeroWidget;
use App\Filament\Widgets\DashboardLayoutCustomizer;
use App\Filament\Widgets\ExecutiveKpiGridWidget;
use App\Filament\Widgets\FiberTopologyWidget;
use App\Filament\Widgets\OnlineUsersChartWidget;
use App\Filament\Widgets\RevenueTrendChartWidget;
use App\Filament\Widgets\SubscriberLifecycleWidget;
use App\Filament\Widgets\UnifiedOperationsWidget;
use App\Services\Dashboard\DashboardPreferencesService;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $navigationGroup = 'Overview';

    protected static ?int $navigationSort = -10;

    public function getWidgets(): array
    {
        $widgets = app(DashboardPreferencesService::class)->widgetsFor(auth()->user());
        $widgets[] = DashboardLayoutCustomizer::class;

        return $widgets;
    }

    public function getColumns(): int|string|array
    {
        $compact = app(DashboardPreferencesService::class)->isCompact(auth()->user());

        return $compact
            ? ['default' => 1, 'sm' => 2, 'xl' => 3]
            : ['default' => 1, 'sm' => 2, 'lg' => 4];
    }

    public function getExtraBodyAttributes(): array
    {
        return [
            'data-isp-dashboard' => '1',
            'data-dashboard-stream' => route('admin.dashboard-stream'),
            'data-tenant-id' => (string) auth()->user()?->tenant_id,
            'class' => app(DashboardPreferencesService::class)->isCompact(auth()->user())
                ? 'isp-dashboard-compact'
                : '',
        ];
    }
}
