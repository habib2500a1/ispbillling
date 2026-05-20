<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasRoleDashboard;
use App\Services\Dashboard\DashboardMetricsService;
use App\Support\SupportPanelAccess;
use Filament\Pages\Page;

class SupportDashboard extends Page
{
    use HasRoleDashboard;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static string $view = 'filament.pages.support-dashboard';

    protected static ?string $navigationLabel = 'Support dashboard';

    protected static ?string $title = 'Support dashboard';

    protected static ?string $navigationGroup = 'Overview';

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return static::staff()->canSupport()
            || SupportPanelAccess::viewTickets(auth()->user());
    }

    /**
     * @return list<array{label: string, value: string, hint: string, class?: string, valueClass?: string}>
     */
    public function getStatCards(): array
    {
        $m = app(DashboardMetricsService::class)->supportSnapshot();

        return [
            ['label' => 'Open tickets', 'value' => (string) $m['open'], 'hint' => 'Needs action', 'class' => 'isp-hub-stat--amber'],
            ['label' => 'SLA breached', 'value' => (string) $m['sla_breached'], 'hint' => 'Past deadline', 'class' => $m['sla_breached'] > 0 ? 'isp-hub-stat--danger' : '', 'valueClass' => $m['sla_breached'] > 0 ? 'isp-hub-stat-value--danger' : ''],
            ['label' => 'Unassigned', 'value' => (string) $m['unassigned'], 'hint' => 'No technician'],
            ['label' => 'Critical', 'value' => (string) $m['critical'], 'hint' => 'Priority critical', 'class' => $m['critical'] > 0 ? 'isp-hub-stat--danger' : ''],
        ];
    }
}
