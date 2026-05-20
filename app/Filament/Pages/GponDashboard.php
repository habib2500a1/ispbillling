<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasRoleDashboard;
use App\Services\Dashboard\DashboardMetricsService;
use Filament\Pages\Page;

class GponDashboard extends Page
{
    use HasRoleDashboard;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string $view = 'filament.pages.gpon-dashboard';

    protected static ?string $navigationLabel = 'GPON dashboard';

    protected static ?string $title = 'GPON / ONU dashboard';

    protected static ?string $navigationGroup = 'Overview';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return static::staff()->canOlt();
    }

    /**
     * @return list<array{label: string, value: string, hint: string, class?: string, valueClass?: string}>
     */
    public function getStatCards(): array
    {
        $m = app(DashboardMetricsService::class)->gponSnapshot();

        return [
            ['label' => 'ONU online', 'value' => (string) ($m['online_onus'] ?? 0), 'hint' => 'Of '.($m['total_onus'] ?? 0).' total', 'class' => 'isp-hub-stat--violet'],
            ['label' => 'Offline ONU', 'value' => (string) ($m['offline_onus'] ?? 0), 'hint' => 'No signal / down'],
            ['label' => 'Critical signal', 'value' => (string) ($m['critical_onus'] ?? 0), 'hint' => 'Needs field visit', 'class' => ($m['critical_onus'] ?? 0) > 0 ? 'isp-hub-stat--danger' : '', 'valueClass' => ($m['critical_onus'] ?? 0) > 0 ? 'isp-hub-stat-value--danger' : ''],
            ['label' => 'Warning', 'value' => (string) ($m['warning_onus'] ?? 0), 'hint' => 'Weak RX/TX'],
            ['label' => 'Open alerts', 'value' => (string) ($m['open_alerts'] ?? 0), 'hint' => 'LOS / signal'],
            ['label' => 'Fiber faults', 'value' => (string) ($m['fiber_faults'] ?? 0), 'hint' => 'Unresolved cuts', 'class' => ($m['fiber_faults'] ?? 0) > 0 ? 'isp-hub-stat--danger' : ''],
            ['label' => 'Avg RX', 'value' => $m['avg_rx_dbm'] !== null ? $m['avg_rx_dbm'].' dBm' : '—', 'hint' => 'Network average'],
            ['label' => 'Health score', 'value' => (string) ($m['avg_health'] ?? 0).'%', 'hint' => 'ONU health index'],
        ];
    }
}
