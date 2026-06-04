<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Services\CallCenter\CallCenterReportService;
use App\Support\SupportPanelAccess;
use Carbon\Carbon;
use Filament\Pages\Page;

class CallCenterReports extends Page
{
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string $view = 'filament.pages.call-center-reports';

    protected static ?string $navigationLabel = 'Call reports';

    protected static ?string $title = 'Call reports';

    protected static ?string $slug = 'call-center-reports';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->setDatePreset('month');
    }

    public static function canAccess(): bool
    {
        return SupportPanelAccess::viewTickets(auth()->user());
    }

    public function setDatePreset(string $preset): void
    {
        if ($preset === 'today') {
            $this->dateFrom = now()->toDateString();
            $this->dateTo = now()->toDateString();

            return;
        }

        if ($preset === 'week') {
            $this->dateFrom = now()->startOfWeek()->toDateString();
            $this->dateTo = now()->toDateString();

            return;
        }

        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRowsProperty(): array
    {
        return app(CallCenterReportService::class)->staffSummary(
            Carbon::parse($this->dateFrom)->startOfDay(),
            Carbon::parse($this->dateTo)->endOfDay(),
        );
    }

    /**
     * @return array{total_calls: int, answered: int, missed: int, outbound: int, inbound: int}
     */
    public function getTotalsProperty(): array
    {
        return app(CallCenterReportService::class)->totals(
            Carbon::parse($this->dateFrom)->startOfDay(),
            Carbon::parse($this->dateTo)->endOfDay(),
        );
    }
}
