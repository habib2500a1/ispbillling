<?php

namespace App\Filament\Support;

use App\Models\MfsSmsRecord;
use App\Models\Payment;
use App\Services\Payments\MfsPaymentTransferService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;

final class TransferMfsPaymentAction
{
    public static function make(string $name = 'transferPayment'): Action
    {
        $action = Action::make($name)
            ->label('Transfer')
            ->icon('heroicon-o-arrow-right-circle')
            ->color('warning')
            ->button()
            ->outlined()
            ->modalHeading('Transfer payment to correct subscriber')
            ->modalDescription(fn (MfsSmsRecord $record): string => self::modalText($record))
            ->form([
                ...AssignSubscriberPaymentAction::formSchema(),
                Forms\Components\Textarea::make('notes')
                    ->label('Reason (optional)')
                    ->rows(2),
            ])
            ->action(function (MfsSmsRecord $record, array $data): void {
                $payment = Payment::query()->withoutGlobalScopes()->find($record->payment_id);
                if ($payment === null) {
                    Notification::make()->title('No payment linked')->danger()->send();

                    return;
                }

                try {
                    app(MfsPaymentTransferService::class)->transfer(
                        $payment,
                        (int) $data['customer_id'],
                        $data['notes'] ?? null,
                        auth()->id(),
                    );
                    Notification::make()
                        ->title('Payment transferred')
                        ->body('Wrong subscriber undone; correct subscriber billed (FIFO).')
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()->title('Transfer failed')->body($e->getMessage())->danger()->send();
                }
            });

        return self::applySmsVisibility($action);
    }

    public static function forPayment(): Action
    {
        $action = Action::make('transferToSubscriber')
            ->label('Transfer')
            ->icon('heroicon-o-arrow-right-circle')
            ->color('warning')
            ->button()
            ->outlined()
            ->modalHeading('Transfer MFS payment to correct subscriber')
            ->form([
                ...AssignSubscriberPaymentAction::formSchema(),
                Forms\Components\Textarea::make('notes')
                    ->label('Reason')
                    ->rows(2)
                    ->placeholder('Wrong Ref / wrong phone match'),
            ])
            ->action(function (Payment $record, array $data): void {
                try {
                    app(MfsPaymentTransferService::class)->transfer(
                        $record,
                        (int) $data['customer_id'],
                        $data['notes'] ?? null,
                        auth()->id(),
                    );
                    Notification::make()->title('Payment transferred')->success()->send();
                } catch (\Throwable $e) {
                    Notification::make()->title('Transfer failed')->body($e->getMessage())->danger()->send();
                }
            });

        return self::applyPaymentVisibility($action);
    }

    private static function applySmsVisibility(Action $action): Action
    {
        return $action
            ->visible(fn (MfsSmsRecord $record): bool => $record->payment_id !== null
                && in_array($record->status, [MfsSmsRecord::STATUS_USED, MfsSmsRecord::STATUS_APPROVED], true))
            ->disabled(function (MfsSmsRecord $record): bool {
                $payment = Payment::query()->withoutGlobalScopes()->find($record->payment_id);

                return $payment === null
                    || app(MfsPaymentTransferService::class)->canTransfer($payment) !== null;
            })
            ->tooltip(function (MfsSmsRecord $record): ?string {
                $payment = Payment::query()->withoutGlobalScopes()->find($record->payment_id);
                if ($payment === null) {
                    return 'No payment linked';
                }

                return app(MfsPaymentTransferService::class)->canTransfer($payment);
            });
    }

    private static function applyPaymentVisibility(Action $action): Action
    {
        return $action
            ->visible(fn (Payment $record): bool => $record->status === 'completed'
                && filled($record->gateway))
            ->disabled(fn (Payment $record): bool => app(MfsPaymentTransferService::class)->canTransfer($record) !== null)
            ->tooltip(fn (Payment $record): ?string => app(MfsPaymentTransferService::class)->canTransfer($record));
    }

    public static function modalText(MfsSmsRecord $record): string
    {
        $payment = $record->payment_id
            ? Payment::query()->withoutGlobalScopes()->with('customer')->find($record->payment_id)
            : null;
        $wrong = $payment?->customer;
        $lines = [
            'Trx: '.$record->transaction_id.' · '.number_format((float) $record->amount, 2).' BDT',
        ];
        if ($wrong !== null) {
            $lines[] = 'Currently on: '.$wrong->customer_code.' — '.$wrong->name.' (will be undone)';
        }
        $lines[] = 'Select the correct subscriber — bills/wallet/network will update on both sides.';

        return implode("\n", $lines);
    }
}
