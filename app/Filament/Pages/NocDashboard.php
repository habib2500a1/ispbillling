<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasRoleDashboard;
use App\Filament\Widgets\BandwidthLiveChartWidget;
use App\Filament\Widgets\BandwidthMonitorStatsWidget;
use App\Filament\Widgets\OnlineUsersChartWidget;
use App\Services\Dashboard\DashboardMetricsService;
use Filament\Pages\Page;

class NocDashboard extends Page
{
    use HasRoleDashboard;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static string $view = 'filament.pages.noc-dashboard';

    protected static ?string $navigationLabel = 'NOC dashboard';

    protected static ?string $title = 'NOC dashboard';

    protected static ?string $navigationGroup = 'Overview';

    protected static ?int $navigationSort = 0;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return static::staff()->canNetwork();
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        return app(DashboardMetricsService::class)->nocSnapshot();
    }

    /**
     * @return list<array{label: string, value: string, hint: string, color?: string}>
     */
    public function getStatCards(): array
    {
        $m = $this->getMetrics();

        return [
            ['label' => 'PPPoE online', 'value' => (string) ($m['online_now'] ?? 0), 'hint' => 'Active sessions', 'class' => 'isp-hub-stat--cyan'],
            ['label' => 'Download (live)', 'value' => ($m['bandwidth_mbps'] ?? 0).' Mbps/s', 'hint' => 'Sum of active PPPoE rates'],
            ['label' => 'MikroTik', 'value' => ($m['mikrotik_online'] ?? 0).'/'.($m['mikrotik_total'] ?? 0), 'hint' => 'Routers online'],
            ['label' => 'OLT / ONU', 'value' => 'OLT '.($m['olts_online'] ?? 0).'/'.($m['olts_total'] ?? 0), 'hint' => 'ONU '.($m['online_onus'] ?? $m['onus_online'] ?? 0).'/'.($m['total_onus'] ?? $m['onus_total'] ?? 0)],
            ['label' => 'Fiber alerts', 'value' => (string) ($m['fiber_alerts'] ?? 0), 'hint' => 'Open optical alerts', 'class' => ($m['fiber_alerts'] ?? 0) > 0 ? 'isp-hub-stat--danger' : ''],
            ['label' => 'Critical ONU', 'value' => (string) ($m['critical_onus'] ?? 0), 'hint' => 'Weak / critical signal', 'valueClass' => ($m['critical_onus'] ?? 0) > 0 ? 'isp-hub-stat-value--danger' : ''],
        ];
    }

    /**
     * @return array<class-string>
     */
    public function getFooterWidgets(): array
    {
        return [
            BandwidthMonitorStatsWidget::class,
            BandwidthLiveChartWidget::class,
            OnlineUsersChartWidget::class,
        ];
    }
}
