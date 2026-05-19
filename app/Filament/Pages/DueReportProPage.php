<?php

namespace App\Filament\Pages;

use App\Services\Reports\AnalyticsReportService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DueReportProPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static string $view = 'filament.pages.due-report-pro';

    protected static ?string $navigationLabel = 'Due Report Pro';

    protected static ?string $title = 'Due Report Pro';

    protected static ?string $slug = 'due-report-pro';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return PaymentsReport::canAccess();
    }

    /**
     * @return array{rows: list<array<string, mixed>>, aging: array<string, float>, count: int}
     */
    public function getReportProperty(): array
    {
        return app(AnalyticsReportService::class)->dueReportPro();
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
        $rows = $this->report['rows'];
        $filename = 'due-report-pro-'.now()->format('Y-m-d-His').'.csv';

        return Response::streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Invoice', 'Customer', 'Code', 'Area', 'Due date', 'Days overdue', 'Aging bucket', 'Balance due', 'Status']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['invoice_number'],
                    $row['customer'],
                    $row['customer_code'],
                    $row['area'],
                    $row['due_date'],
                    $row['days_overdue'],
                    $row['aging_bucket'],
                    $row['balance_due'],
                    $row['status'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
