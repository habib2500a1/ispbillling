<?php

namespace App\Filament\Resources\SalesLeadResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\SalesLeadResource;
use App\Models\SalesLead;
use App\Services\Sales\SalesLeadConversionService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalesLead extends EditRecord
{
    protected static string $resource = SalesLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('convert')
                ->label('Convert to customer')
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->converted_customer_id === null
                    && $this->record->status !== SalesLead::STATUS_LOST)
                ->action(function (): void {
                    $customer = app(SalesLeadConversionService::class)->convert($this->record);
                    $this->redirect(CustomerResource::getUrl('view', ['record' => $customer]));
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
