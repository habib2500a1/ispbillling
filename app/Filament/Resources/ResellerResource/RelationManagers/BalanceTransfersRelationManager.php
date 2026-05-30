<?php

namespace App\Filament\Resources\ResellerResource\RelationManagers;

use App\Models\Reseller;
use App\Models\ResellerBalanceTransfer;
use App\Services\Resellers\ResellerBalanceService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BalanceTransfersRelationManager extends RelationManager
{
    /**
     * Use a custom relationship that shows BOTH incoming and outgoing transfers.
     * The original 'balanceTransfersIn' only showed incoming transfers — a bug.
     */
    protected static string $relationship = 'balanceTransfersIn';

    protected static ?string $title = 'Balance transfers';

    protected static ?string $icon = 'heroicon-o-arrows-right-left';

    /**
     * Override to show both incoming AND outgoing transfers for this reseller.
     */
    protected function getTableQuery(): Builder
    {
        $resellerId = $this->getOwnerRecord()->getKey();

        return ResellerBalanceTransfer::query()
            ->where(function (Builder $q) use ($resellerId): void {
                $q->where('to_reseller_id', $resellerId)
                    ->orWhere('from_reseller_id', $resellerId);
            })
            ->with(['fromReseller', 'toReseller', 'creator']);
    }

    public function table(Table $table): Table
    {
        $resellerId = (int) $this->getOwnerRecord()->getKey();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('direction')
                    ->label('Direction')
                    ->badge()
                    ->getStateUsing(function (ResellerBalanceTransfer $record) use ($resellerId): string {
                        if ((int) $record->to_reseller_id === $resellerId) {
                            return 'IN';
                        }

                        return 'OUT';
                    })
                    ->color(fn (string $state): string => $state === 'IN' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('counterparty')
                    ->label('From / To')
                    ->getStateUsing(function (ResellerBalanceTransfer $record) use ($resellerId): string {
                        if ((int) $record->to_reseller_id === $resellerId) {
                            return $record->fromReseller?->name ?? 'HQ / System';
                        }

                        return $record->toReseller?->name ?? '—';
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->money('BDT')
                    ->color(fn (ResellerBalanceTransfer $record): string => (int) $record->to_reseller_id === $resellerId ? 'success' : 'danger')
                    ->formatStateUsing(function (ResellerBalanceTransfer $record) use ($resellerId): string {
                        $prefix = (int) $record->to_reseller_id === $resellerId ? '+' : '-';

                        return $prefix.number_format((float) $record->amount, 2).' BDT';
                    }),
                Tables\Columns\TextColumn::make('transfer_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (ResellerBalanceTransfer $record): string => ResellerBalanceTransfer::typeLabel((string) $record->transfer_type)),
                Tables\Columns\TextColumn::make('reference')
                    ->fontFamily('mono')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('notes')
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('By')
                    ->placeholder('System')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('transfer_out')
                    ->label('Transfer to another reseller')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('to_reseller_id')
                            ->label('Transfer to')
                            ->options(fn (): array => Reseller::query()
                                ->where('id', '!=', $resellerId)
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->prefix('BDT')
                            ->helperText(fn (): string => 'Available: '.number_format((float) $this->getOwnerRecord()->wallet_balance, 2).' BDT'),
                        Forms\Components\Textarea::make('notes')->rows(2),
                    ])
                    ->action(function (array $data, ResellerBalanceService $balances): void {
                        /** @var Reseller $from */
                        $from = $this->getOwnerRecord();
                        $to = Reseller::query()->findOrFail($data['to_reseller_id']);

                        if ((float) $data['amount'] > (float) $from->wallet_balance) {
                            Notification::make()
                                ->title('Insufficient balance')
                                ->body('Wallet balance is '.number_format((float) $from->wallet_balance, 2).' BDT')
                                ->danger()
                                ->send();

                            return;
                        }

                        $balances->transfer($from, $to, (float) $data['amount'], $data['notes'] ?? null);
                        Notification::make()->title('Balance transferred')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No transfers yet')
            ->emptyStateDescription('Balance transfers (incoming and outgoing) will appear here.')
            ->emptyStateIcon('heroicon-o-arrows-right-left');
    }
}
