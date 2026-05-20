<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\Billing\PaymentVoidService;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPayment extends EditRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('void')
                ->label('Void / delete')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->form([
                    Textarea::make('reason')->label('Reason')->rows(2),
                ])
                ->visible(fn (): bool => $this->record instanceof Payment && app(PaymentVoidService::class)->canVoid($this->record))
                ->action(function (array $data): void {
                    /** @var Payment $payment */
                    $payment = $this->record;
                    app(PaymentVoidService::class)->void($payment, $data['reason'] ?? null);
                    Notification::make()->title('Payment voided')->success()->send();
                    $this->redirect(PaymentResource::getUrl('index'));
                }),
        ];
    }
}
