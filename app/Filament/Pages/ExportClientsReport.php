<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportClientsReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static string $view = 'filament.pages.export-clients-report';

    protected static ?string $navigationLabel = 'Export Clients';

    protected static ?string $title = 'Export Clients';

    protected static ?string $slug = 'export-clients-report';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return PaymentsReport::canAccess();
    }

    public function getCustomerCountProperty(): int
    {
        return (int) Customer::query()->count();
    }

    public function getActiveCountProperty(): int
    {
        return (int) Customer::query()->where('status', 'active')->count();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Download CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    protected function exportCsv(): StreamedResponse
    {
        $filename = 'clients-export-'.now()->format('Y-m-d-His').'.csv';

        return Response::streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Customer code', 'Name', 'Phone', 'Email', 'Status', 'Package', 'Area', 'Zone',
                'Address', 'Joined', 'Balance due',
            ]);

            Customer::query()
                ->with(['package', 'area', 'zone'])
                ->orderBy('customer_code')
                ->chunk(200, function ($customers) use ($handle): void {
                    foreach ($customers as $customer) {
                        /** @var Customer $customer */
                        fputcsv($handle, [
                            $customer->customer_code,
                            $customer->name,
                            $customer->phone,
                            $customer->email,
                            $customer->statusLabel(),
                            $customer->package?->name,
                            $customer->area?->name,
                            $customer->zone?->name,
                            $customer->address,
                            $customer->joined_at?->format('Y-m-d'),
                            $customer->openInvoiceBalance(),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
