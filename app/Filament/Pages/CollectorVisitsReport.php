<?php

namespace App\Filament\Pages;

use App\Services\Collector\CollectorVisitsReportService;
use Carbon\Carbon;
use Filament\Pages\Page;

class CollectorVisitsReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static string $view = 'filament.pages.collector-visits-report';

    protected static ?string $navigationLabel = 'Collector visits';

    protected static ?string $title = 'Collector visits & GPS map';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 5;

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = now()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super-admin', 'isp-admin', 'isp-manager', 'admin', 'cashier']) ?? false;
    }

    /** @return array<string, mixed> */
    public function getReport(): array
    {
        $from = Carbon::parse($this->dateFrom ?: now()->toDateString());
        $to = Carbon::parse($this->dateTo ?: now()->toDateString());

        return app(CollectorVisitsReportService::class)->report($from, $to);
    }
}
