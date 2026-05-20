<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasRoleDashboard;
use App\Filament\Widgets\AgedReceivablesWidget;
use App\Filament\Widgets\RevenueTrendChartWidget;
use App\Services\Dashboard\DashboardMetricsService;
use Filament\Pages\Page;

class BillingDashboard extends Page
{
    use HasRoleDashboard;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static string $view = 'filament.pages.billing-dashboard';

    protected static ?string $navigationLabel = 'Billing dashboard';

    protected static ?string $title = 'Billing dashboard';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return static::staff()->canBilling();
    }

    /**
     * @return list<array{label: string, value: string, hint: string, color?: string}>
     */
    public function getStatCards(): array
    {
        $m = app(DashboardMetricsService::class)->billingSnapshot();

        return [
            ['label' => 'Collected (MTD)', 'value' => number_format((float) ($m['collected'] ?? 0), 0).' BDT', 'hint' => 'This month', 'class' => 'isp-hub-stat--emerald'],
            ['label' => 'Today collection', 'value' => number_format((float) ($m['collected_today'] ?? 0), 0).' BDT', 'hint' => 'Completed payments'],
            ['label' => 'Outstanding', 'value' => number_format((float) ($m['outstanding'] ?? 0), 0).' BDT', 'hint' => 'Total due', 'class' => 'isp-hub-stat--amber'],
            ['label' => 'Due customers', 'value' => (string) ($m['due_customers'] ?? 0), 'hint' => 'With open balance'],
            ['label' => 'Open invoices', 'value' => (string) ($m['open_invoices'] ?? 0), 'hint' => 'Draft / open / partial'],
            ['label' => 'Unpaid subs', 'value' => (string) ($m['unpaid'] ?? 0), 'hint' => 'Active with due'],
        ];
    }

    /**
     * @return array<class-string>
     */
    public function getFooterWidgets(): array
    {
        return [
            RevenueTrendChartWidget::class,
            AgedReceivablesWidget::class,
        ];
    }
}
