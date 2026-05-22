<?php

namespace App\Filament\Support;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PendingGatewayPayment;
use App\Services\Payments\GatewayPaymentVerificationService;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;

final class AssignSubscriberPaymentAction
{
    public static function make(string $name = 'assignSubscriber'): Action
    {
        return Action::make($name)
            ->label('Assign subscriber ID')
            ->icon('heroicon-o-user-plus')
            ->color('warning')
            ->modalHeading('Link payment to subscriber')
            ->modalDescription(fn (PendingGatewayPayment $record): string => self::hintText($record))
            ->form(self::formSchema())
            ->action(function (PendingGatewayPayment $record, array $data): void {
                try {
                    app(GatewayPaymentVerificationService::class)->assignAndApprove(
                        $record,
                        (int) $data['customer_id'],
                        isset($data['invoice_id']) ? (int) $data['invoice_id'] : null,
                        auth()->id(),
                    );
                    Notification::make()
                        ->title('Payment applied to subscriber')
                        ->body('Bills paid FIFO; any extra goes to wallet/advance.')
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()->title('Could not assign')->body($e->getMessage())->danger()->send();
                }
            });
    }

    /**
     * @return list<Forms\Components\Component>
     */
    public static function formSchema(): array
    {
        return [
            Forms\Components\Select::make('customer_id')
                ->label('Subscriber ID')
                ->searchable()
                ->getSearchResultsUsing(function (string $search): array {
                    return Customer::query()
                        ->where(function ($q) use ($search): void {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('customer_code', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%")
                                ->orWhere('mikrotik_secret_name', 'like', "%{$search}%")
                                ->orWhere('radius_username', 'like', "%{$search}%");
                        })
                        ->orderBy('customer_code')
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (Customer $c): array => [
                            $c->id => $c->customer_code.' — '.$c->name,
                        ])
                        ->all();
                })
                ->getOptionLabelUsing(fn ($value): ?string => Customer::query()->find($value)?->customer_code.' — '.Customer::query()->find($value)?->name)
                ->required()
                ->live(),
            Forms\Components\Select::make('invoice_id')
                ->label('Invoice (optional — leave empty for FIFO on all open bills)')
                ->options(function (Get $get): array {
                    $customerId = $get('customer_id');
                    if (! $customerId) {
                        return [];
                    }

                    return Invoice::query()
                        ->where('customer_id', $customerId)
                        ->whereIn('status', ['open', 'partial', 'draft'])
                        ->orderByDesc('issue_date')
                        ->get()
                        ->mapWithKeys(fn (Invoice $inv): array => [
                            $inv->id => $inv->invoice_number.' — due '.number_format($inv->balanceDue(), 2).' BDT',
                        ])
                        ->all();
                })
                ->searchable()
                ->nullable(),
        ];
    }

    public static function hintText(PendingGatewayPayment $record): string
    {
        $parts = [];
        $token = $record->meta['reference_token'] ?? null;
        if ($token) {
            $parts[] = 'SMS reference scanned: '.$token;
        }
        $phone = $record->meta['sender_phone'] ?? null;
        if ($phone) {
            $parts[] = 'Sender phone: '.$phone;
        }
        $preview = $record->meta['raw_message_preview'] ?? null;
        if ($preview) {
            $parts[] = 'Message: '.$preview;
        }

        return $parts !== [] ? implode("\n", $parts) : 'No subscriber ID or phone matched automatically.';
    }
}
