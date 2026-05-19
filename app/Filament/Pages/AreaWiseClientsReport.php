<?php

namespace App\Filament\Pages;

use App\Services\Reports\AnalyticsReportService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AreaWiseClientsReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static string $view = 'filament.pages.area-wise-clients-report';

    protected static ?string $navigationLabel = 'Area-wise Client';

    protected static ?string $title = 'Area-wise Clients';

    protected static ?string $slug = 'area-wise-clients-report';

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
        return app(AnalyticsReportService::class)->areaWiseReport();
    }

    /**
     * @return array{areas: int, active: int, collected: float, due: float}
     */
    public function getStatsProperty(): array
    {
        $rows = $this->rows;

        return [
            'areas' => count($rows),
            'active' => (int) array_sum(array_column($rows, 'active')),
            'collected' => round((float) array_sum(array_column($rows, 'collected_mtd')), 2),
            'due' => round((float) array_sum(array_column($rows, 'outstanding')), 2),
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
        $filename = 'area-wise-clients-'.now()->format('Y-m-d-His').'.csv';

        return Response::streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Area', 'Code', 'Active', 'Total customers', 'Collected (MTD)', 'Outstanding']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['area'],
                    $row['code'],
                    $row['active'],
                    $row['total_customers'],
                    $row['collected_mtd'],
                    $row['outstanding'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
