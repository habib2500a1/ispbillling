<?php

namespace App\Filament\Support;

use App\Filament\Pages\BillCollectionDesk;
use App\Jobs\SyncCustomerOnuFromOltJob;
use App\Models\Customer;
use App\Models\Reseller;
use App\Services\Billing\CustomerWalletService;
use App\Services\Optical\OnuNetworkDiagnosticsPresenter;
use App\Services\Subscribers\AdminSubscriberTransferService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;

/**
 * Move / Recharge / Retest / Collect — shared between directory table and client 360 view.
 */
final class ShebaSubscriberQuickActions
{
    /**
     * @return list<Tables\Actions\Action>
     */
    public static function tableActions(): array
    {
        return [
            static::moveResellerTableAction(),
            static::rechargeWalletTableAction(),
            static::retestOnuTableAction(),
            Tables\Actions\Action::make('collect_due')
                ->label('Collect payment')
                ->icon('heroicon-o-currency-bangladeshi')
                ->color('warning')
                ->url(fn (Customer $record): string => BillCollectionDesk::getUrl().'?customer='.$record->getKey()),
        ];
    }

    /**
     * @return list<Actions\Action>
     */
    /**
     * @return list<Actions\Action>
     */
    public static function headerActions(Customer $customer, bool $includeCollect = false): array
    {
        $actions = [
            static::moveResellerHeaderAction($customer),
            static::rechargeWalletHeaderAction($customer),
            static::retestOnuHeaderAction($customer),
        ];

        if ($includeCollect) {
            $actions[] = Actions\Action::make('collect_due')
                ->label('Collect payment')
                ->icon('heroicon-o-currency-bangladeshi')
                ->color('warning')
                ->url(BillCollectionDesk::getUrl().'?customer='.$customer->getKey());
        }

        return $actions;
    }

    public static function moveResellerTableAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('move_reseller')
            ->label('Move')
            ->icon('heroicon-o-arrows-right-left')
            ->color('gray')
            ->tooltip('Move to POP / reseller')
            ->form(static::moveFormSchema())
            ->action(fn (Customer $record, array $data) => static::runMove($record, $data));
    }

    public static function moveResellerHeaderAction(Customer $customer): Actions\Action
    {
        return Actions\Action::make('move_reseller')
            ->label('Move')
            ->icon('heroicon-o-arrows-right-left')
            ->color('gray')
            ->form(static::moveFormSchema())
            ->action(fn (array $data) => static::runMove($customer, $data));
    }

    public static function rechargeWalletTableAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('recharge_wallet')
            ->label('Recharge')
            ->icon('heroicon-o-wallet')
            ->color('success')
            ->tooltip('Add wallet balance')
            ->form(static::rechargeFormSchema())
            ->action(fn (Customer $record, array $data) => static::runRecharge($record, $data));
    }

    public static function rechargeWalletHeaderAction(Customer $customer): Actions\Action
    {
        return Actions\Action::make('recharge_wallet')
            ->label('Recharge wallet')
            ->icon('heroicon-o-wallet')
            ->color('success')
            ->form(static::rechargeFormSchema())
            ->action(fn (array $data) => static::runRecharge($customer, $data));
    }

    public static function retestOnuTableAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('retest_onu')
            ->label('Retest')
            ->icon('heroicon-o-signal')
            ->color('info')
            ->tooltip('Run ONU diagnostics')
            ->action(fn (Customer $record) => static::runRetest($record));
    }

    public static function retestOnuHeaderAction(Customer $customer): Actions\Action
    {
        return Actions\Action::make('retest_onu')
            ->label('Retest ONU')
            ->icon('heroicon-o-signal')
            ->color('info')
            ->action(fn () => static::runRetest($customer));
    }

    /**
     * @return list<Forms\Components\Component>
     */
    private static function moveFormSchema(): array
    {
        return [
            Forms\Components\Select::make('reseller_id')
                ->label('Target reseller')
                ->options(fn (): array => Reseller::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->nullable()
                ->helperText('Leave empty to detach from reseller.'),
            Forms\Components\Textarea::make('reason')->rows(2),
        ];
    }

    /**
     * @return list<Forms\Components\Component>
     */
    private static function rechargeFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('amount')
                ->label('Amount (BDT)')
                ->numeric()
                ->required()
                ->minValue(1),
            Forms\Components\Textarea::make('notes')->rows(2),
        ];
    }

  /**
     * @param  array<string, mixed>  $data
     */
    private static function runMove(Customer $record, array $data): void
    {
        $to = filled($data['reseller_id'] ?? null)
            ? Reseller::query()->find((int) $data['reseller_id'])
            : null;
        app(AdminSubscriberTransferService::class)->moveToReseller(
            $record,
            $to,
            auth()->user(),
            $data['reason'] ?? null,
        );
        Notification::make()->title('Subscriber moved')->success()->send();
    }

      /**
     * @param  array<string, mixed>  $data
     */
    private static function runRecharge(Customer $record, array $data): void
    {
        $payment = app(CustomerWalletService::class)->deposit(
            $record,
            (float) $data['amount'],
            $data['note'] ?? $data['notes'] ?? null,
            auth()->id(),
        );
        Notification::make()
            ->title('Wallet recharged')
            ->body('Receipt '.$payment->receipt_number.' · Balance BDT '.number_format((float) $record->fresh()->account_balance, 2))
            ->success()
            ->send();
    }

    private static function runRetest(Customer $record): void
    {
        SyncCustomerOnuFromOltJob::dispatch(
            (int) $record->tenant_id,
            (int) $record->id,
            forceOltSync: true,
        );
        $diag = app(OnuNetworkDiagnosticsPresenter::class)->forCustomer($record->fresh() ?? $record);
        $rx = is_array($diag) ? ($diag['onu']['rx_display'] ?? 'No ONU linked') : 'No ONU linked';
        Notification::make()
            ->title('Line retest queued')
            ->body('ONU RX: '.$rx)
            ->success()
            ->send();
    }
}
