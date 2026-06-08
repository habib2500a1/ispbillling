<?php

namespace App\Filament\Resources\PlatformInvoiceResource\Pages;

use App\Filament\Resources\PlatformInvoiceResource;
use App\Models\PlatformInvoice;
use App\Services\Tenant\PlatformInvoicePaymentService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPlatformInvoice extends ViewRecord
{
    protected static string $resource = PlatformInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pay_online')
                ->label('Pay online')
                ->icon('heroicon-o-credit-card')
                ->color('primary')
                ->url(fn (): string => app(PlatformInvoicePaymentService::class)->paymentUrl($this->record))
                ->openUrlInNewTab()
                ->visible(fn (): bool => $this->record instanceof PlatformInvoice
                    && ! $this->record->isPaid()
                    && $this->record->status !== PlatformInvoice::STATUS_VOID),
        ];
    }
}
