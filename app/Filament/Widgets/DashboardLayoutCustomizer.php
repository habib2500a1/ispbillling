<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardPreferencesService;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class DashboardLayoutCustomizer extends Widget
{
    protected static string $view = 'filament.widgets.dashboard-layout-customizer';

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 99;

    protected int|string|array $columnSpan = 'full';

    /** @var list<string> */
    public array $selected = [];

    public bool $compact = true;

    public function mount(): void
    {
        $user = auth()->user();
        $service = app(DashboardPreferencesService::class);
        $this->selected = $service->widgetsFor($user);
        $this->compact = $service->isCompact($user);
    }

    /**
     * @return array<string, string>
     */
    public function widgetOptions(): array
    {
        return [
            \App\Filament\Widgets\BillingExecutiveDashboardWidget::class => 'Billing overview (KPIs + chart)',
            \App\Filament\Widgets\DashboardHeroWidget::class => 'Hero banner',
            \App\Filament\Widgets\ExecutiveKpiGridWidget::class => 'KPI wall (20 cards)',
            \App\Filament\Widgets\UnifiedOperationsWidget::class => 'All departments',
            \App\Filament\Widgets\SmartOpsCommandCenterWidget::class => 'Smart ops command center',
            \App\Filament\Widgets\MikrotikFleetHealthWidget::class => 'MikroTik fleet health',
            \App\Filament\Widgets\MikrotikSessionIntegrityWidget::class => 'PPP session integrity',
            \App\Filament\Widgets\DashboardCommandStripWidget::class => 'Quick tools strip',
            \App\Filament\Widgets\SubscriberLifecycleWidget::class => 'Subscriber lifecycle',
            \App\Filament\Widgets\RevenueTrendChartWidget::class => 'Revenue chart',
            \App\Filament\Widgets\OnlineUsersChartWidget::class => 'Online users chart',
            \App\Filament\Widgets\BandwidthLiveCompareStatsWidget::class => 'WAN vs users (live)',
            \App\Filament\Widgets\BandwidthLiveChartWidget::class => 'WAN vs users chart',
            \App\Filament\Widgets\FiberTopologyWidget::class => 'Fiber topology',
        ];
    }

    public function saveLayout(): void
    {
        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $classes = array_values(array_filter(
            $this->selected,
            fn (string $c): bool => array_key_exists($c, $this->widgetOptions()),
        ));

        app(DashboardPreferencesService::class)->saveWidgets($user, $classes);

        $prefs = $user->dashboard_preferences ?? [];
        $prefs['compact'] = $this->compact;
        $user->forceFill(['dashboard_preferences' => $prefs])->save();

        Notification::make()->title('Dashboard layout saved')->success()->send();

        $this->redirect(\App\Filament\Pages\Dashboard::getUrl());
    }
}
