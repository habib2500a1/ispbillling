<?php

namespace App\Filament\Pages;

use App\Services\Reports\AnalyticsReportService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DueReportPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string $view = 'filament.pages.due-report';

    protected static ?string $navigationLabel = 'Due Report';

    protected static ?string $title = 'Due Report';

    protected static ?string $slug = 'due-report';

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
        return app(AnalyticsReportService::class)->dueReport();
    }

    /**
     * @return array{invoices: int, total_due: float, overdue_count: int}
     */
    public function getStatsProperty(): array
    {
        $rows = $this->rows;
        $totalDue = array_sum(array_column($rows, 'balance_due'));
        $overdue = count(array_filter($rows, fn (array $r): bool => ($r['days_overdue'] ?? 0) > 0));

        return [
            'invoices' => count($rows),
            'total_due' => round($totalDue, 2),
            'overdue_count' => $overdue,
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
        $filename = 'due-report-'.now()->format('Y-m-d-His').'.csv';

        return Response::streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Invoice', 'Customer', 'Code', 'Area', 'Due date', 'Days overdue', 'Balance due', 'Status']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['invoice_number'],
                    $row['customer'],
                    $row['customer_code'],
                    $row['area'],
                    $row['due_date'],
                    $row['days_overdue'],
                    $row['balance_due'],
                    $row['status'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
