<?php

namespace App\Filament\Resources\ResellerResource\RelationManagers;

use App\Models\ResellerCommission;
use App\Models\ResellerWalletRechargeRequest;
use App\Services\Resellers\ResellerWalletRechargeService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class WalletRechargesRelationManager extends RelationManager
{
    protected static string $relationship = 'walletRechargeRequests';

    protected static ?string $title = 'Wallet top-ups';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('request_number')
            ->columns([
                Tables\Columns\TextColumn::make('request_number')->searchable(),
                Tables\Columns\TextColumn::make('amount')->money('BDT')->weight('bold')->label('Top-up'),
                Tables\Columns\TextColumn::make('owner_wallet')
                    ->label('Wallet now')
                    ->state(fn (): float => (float) $this->getOwnerRecord()->wallet_balance)
                    ->money('BDT'),
                Tables\Columns\TextColumn::make('wallet_after')
                    ->label('Wallet after approve')
                    ->state(fn (ResellerWalletRechargeRequest $record): ?float => $record->status === ResellerWalletRechargeRequest::STATUS_PENDING
                        ? (float) $this->getOwnerRecord()->wallet_balance + (float) $record->amount
                        : null)
                    ->money('BDT')
                    ->color('success')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('pending_commission')
                    ->label('Commission due')
                    ->state(fn (): float => (float) ResellerCommission::query()
                        ->where('reseller_id', $this->getOwnerRecord()->getKey())
                        ->where('status', ResellerCommission::STATUS_PENDING)
                        ->sum('commission_amount'))
                    ->money('BDT')
                    ->color('warning')
                    ->description('Settle via Commission tab'),
                Tables\Columns\TextColumn::make('payment_method')->label('Method')->badge(),
                Tables\Columns\TextColumn::make('reference')->label('Trx / ref')->limit(24),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
                Tables\Columns\TextColumn::make('reviewed_at')->dateTime(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ResellerWalletRechargeRequest $record): bool => $record->status === ResellerWalletRechargeRequest::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->modalHeading('Approve wallet top-up')
                    ->modalDescription(function (ResellerWalletRechargeRequest $record): string {
                        $reseller = $this->getOwnerRecord();
                        $commissionDue = (float) ResellerCommission::query()
                            ->where('reseller_id', $reseller->getKey())
                            ->where('status', ResellerCommission::STATUS_PENDING)
                            ->sum('commission_amount');
                        $walletAfter = (float) $reseller->wallet_balance + (float) $record->amount;

                        return sprintf(
                            "Credit %s BDT (%s)?\nWallet: %s → %s BDT after approve.\nCommission due (unsettled): %s BDT.",
                            number_format((float) $record->amount, 2),
                            $record->request_number,
                            number_format((float) $reseller->wallet_balance, 2),
                            number_format($walletAfter, 2),
                            number_format($commissionDue, 2),
                        );
                    })
                    ->action(function (ResellerWalletRechargeRequest $record): void {
                        try {
                            app(ResellerWalletRechargeService::class)->approve($record, auth()->user());
                            Notification::make()->title('Wallet credited')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Could not approve')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ResellerWalletRechargeRequest $record): bool => $record->status === ResellerWalletRechargeRequest::STATUS_PENDING)
                    ->form([
                        Forms\Components\Textarea::make('reason')->required()->label('Rejection reason'),
                    ])
                    ->action(function (ResellerWalletRechargeRequest $record, array $data): void {
                        app(ResellerWalletRechargeService::class)->reject($record, auth()->user(), $data['reason']);
                        Notification::make()->title('Top-up rejected')->success()->send();
                    }),
            ]);
    }
}
