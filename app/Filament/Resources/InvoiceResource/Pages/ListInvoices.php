<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('run_billing')
                ->label('Run auto billing')
                ->icon('heroicon-o-bolt')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Generates invoices for active subscribers whose billing day is today (use --force on CLI for all).')
                ->action(function (): void {
                    Artisan::call('isp:generate-bills', ['--date' => now()->toDateString()]);
                    $output = trim(Artisan::output());
                    Notification::make()
                        ->title('Billing run finished')
                        ->body($output !== '' ? $output : 'Check server log for details.')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('apply_late_fees')
                ->label('Apply late fees')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    Artisan::call('isp:apply-late-fees');
                    Notification::make()
                        ->title('Late fees processed')
                        ->body(trim(Artisan::output()))
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make()
                ->label('New invoice'),
        ];
    }
}
