<?php

namespace App\Filament\Pages;

use App\Services\Reports\BtrcDisReportService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BtrcReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static string $view = 'filament.pages.btrc-report';

    protected static ?string $navigationLabel = 'BTRC DIS report';

    protected static ?string $title = 'BTRC DIS report';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 5;

    protected static bool $shouldRegisterNavigation = false;

    public int $rowCount = 0;

    public function mount(): void
    {
        $this->rowCount = app(BtrcDisReportService::class)->rows()->count();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Download CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn (): StreamedResponse => $this->streamCsv()),
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('super-admin') || $user->hasRole('isp-admin') || $user->hasRole('isp-manager'));
    }

    protected function streamCsv(): StreamedResponse
    {
        $service = app(BtrcDisReportService::class);
        $headers = $service->headers();
        $filename = 'btrc-dis-'.now()->format('Y-m').'.csv';

        return response()->streamDownload(function () use ($service, $headers): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($service->rows() as $row) {
                fputcsv($out, array_map(fn (string $h) => $row[$h] ?? '', $headers));
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
