<?php

namespace App\Filament\Resources\ResellerResource\RelationManagers;

use App\Models\ResellerCommission;
use App\Services\Resellers\ResellerCommissionService;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class CommissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'commissions';

    protected static ?string $title = 'Commission ledger';

    protected static ?string $icon = 'heroicon-o-banknotes';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('earned_at')
                    ->label('Earned')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Subscriber')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('gross_amount')
                    ->money('BDT')
                    ->label('Payment')
                    ->sortable(),
                Tables\Columns\TextColumn::make('commission_amount')
                    ->money('BDT')
                    ->label('Commission')
                    ->weight('bold')
                    ->color('success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent_share_amount')
                    ->money('BDT')
                    ->label('Parent share')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ResellerCommission::STATUS_PAID => 'success',
                        ResellerCommission::STATUS_PENDING => 'warning',
                        ResellerCommission::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Paid')
                    ->dateTime('d M Y')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('earned_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        ResellerCommission::STATUS_PENDING => 'Pending',
                        ResellerCommission::STATUS_PAID => 'Paid',
                        ResellerCommission::STATUS_CANCELLED => 'Cancelled',
                    ]),
                Tables\Filters\Filter::make('earned_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('Earned from'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Earned until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('earned_at', '>=', $date))
                            ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('earned_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('statement')
                    ->label('PDF')
                    ->icon('heroicon-o-document')
                    ->url(fn (ResellerCommission $record): string => route('admin.reseller-commissions.statement', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('payout')
                    ->label('Pay to wallet')
                    ->icon('heroicon-o-wallet')
                    ->color('success')
                    ->visible(fn (ResellerCommission $record): bool => $record->status === ResellerCommission::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->modalDescription('This credits the commission amount to the reseller wallet and marks it paid.')
                    ->action(function (ResellerCommission $record, ResellerCommissionService $service): void {
                        $service->payoutToWallet($record);
                        Notification::make()->title('Commission paid to wallet')->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_payout')
                    ->label('Pay selected to wallet')
                    ->icon('heroicon-o-wallet')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Pay commissions to wallet')
                    ->modalDescription('Only pending commissions in your selection will be paid out.')
                    ->action(function (Collection $records, ResellerCommissionService $service): void {
                        $paid = 0;
                        $total = 0.0;
                        foreach ($records as $record) {
                            if ($record->status === ResellerCommission::STATUS_PENDING) {
                                $total += (float) $record->commission_amount;
                                $service->payoutToWallet($record);
                                $paid++;
                            }
                        }

                        Notification::make()
                            ->title($paid > 0 ? 'Commissions paid out' : 'Nothing to pay')
                            ->body($paid > 0
                                ? "Paid {$paid} commission(s) totalling ".number_format($total, 2).' BDT to wallet.'
                                : 'No pending commissions were selected.')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }
}
