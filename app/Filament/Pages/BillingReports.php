<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\MonthlyReportStats;
use Filament\Pages\Page;

class BillingReports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.pages.billing-reports';

    protected static ?string $navigationLabel = 'Monthly reports';

    protected static ?string $title = 'Revenue & collections';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('super-admin') || $user->hasRole('isp-admin'));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Widgets\AgedReceivablesWidget::class,
            \App\Filament\Widgets\ArpuWidget::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|string|array
    {
        return 1;
    }

    /**
     * @return array<class-string<\Filament\Widgets\Widget> | \Filament\Widgets\WidgetConfiguration>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            MonthlyReportStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 1;
    }
}
