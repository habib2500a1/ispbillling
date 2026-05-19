<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadPdf')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (): string => route('invoices.pdf', ['invoice' => $this->getRecord()]))
                ->openUrlInNewTab(),
            Actions\Action::make('bkashPay')
                ->label('Pay with bKash')
                ->icon('heroicon-o-banknotes')
                ->url(fn (): string => route('bkash.invoice.initiate', ['invoice' => $this->getRecord()]))
                ->visible(fn (): bool => \App\Support\BkashSettings::isEnabledForChannel(\App\Support\BkashSettings::CHANNEL_ADMIN)),
            Actions\DeleteAction::make(),
        ];
    }
}
