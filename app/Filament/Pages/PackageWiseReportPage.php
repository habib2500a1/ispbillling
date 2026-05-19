<?php

namespace App\Filament\Pages;

use App\Services\Reports\AnalyticsReportService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PackageWiseReportPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string $view = 'filament.pages.package-wise-report';

    protected static ?string $navigationLabel = 'Package-wise Report';

    protected static ?string $title = 'Package-wise Report';

    protected static ?string $slug = 'package-wise-report';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return PaymentsReport::canAccess();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRowsProperty(): array
    {
        return app(AnalyticsReportService::class)->packagePopularity();
    }

    /**
     * @return array{packages: int, active: int, mrr: float}
     */
    public function getStatsProperty(): array
    {
        $rows = $this->rows;

        return [
            'packages' => count($rows),
            'active' => (int) array_sum(array_column($rows, 'active')),
            'mrr' => round((float) array_sum(array_column($rows, 'est_mrr')), 2),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    protected function exportCsv(): StreamedResponse
    {
        $rows = $this->rows;
        $filename = 'package-wise-report-'.now()->format('Y-m-d-His').'.csv';

        return Response::streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Package', 'Speed', 'Price', 'Subscribers', 'Active', 'Est. MRR']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['package'],
                    $row['speed'],
                    $row['price'],
                    $row['subscribers'],
                    $row['active'],
                    $row['est_mrr'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
