<?php

namespace App\Filament\Pages;

use App\Services\Reports\AnalyticsReportService;
use Carbon\Carbon;
use Filament\Pages\Page;

class ReportsHub extends Page
{
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static string $view = 'filament.pages.reports-hub';

    protected static ?string $navigationLabel = 'Reports & analytics';

    protected static ?string $title = 'Reports & analytics';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 0;

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $service = app(AnalyticsReportService::class);
        $from = now()->startOfMonth();
        $to = now()->endOfMonth();

        return $service->summary($from, $to);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && \App\Support\Rbac\StaffCapability::for($user)->canReports();
    }
}
