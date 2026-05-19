<?php

namespace App\Filament\Resources\ResellerResource\RelationManagers;

use App\Models\ResellerCommission;
use App\Services\Resellers\ResellerCommissionService;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CommissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'commissions';

    protected static ?string $title = 'Commission ledger';

    protected static ?string $icon = 'heroicon-o-banknotes';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('earned_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->label('Subscriber'),
                Tables\Columns\TextColumn::make('gross_amount')->money('BDT'),
                Tables\Columns\TextColumn::make('commission_amount')->money('BDT')->label('Commission'),
                Tables\Columns\TextColumn::make('parent_share_amount')->money('BDT')->label('Parent share'),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->defaultSort('earned_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('statement')
                    ->label('PDF')
                    ->icon('heroicon-o-document')
                    ->url(fn (ResellerCommission $record): string => route('admin.reseller-commissions.statement', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('payout')
                    ->label('Pay to wallet')
                    ->icon('heroicon-o-wallet')
                    ->visible(fn (ResellerCommission $record): bool => $record->status === ResellerCommission::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->action(function (ResellerCommission $record, ResellerCommissionService $service): void {
                        $service->payoutToWallet($record);
                        Notification::make()->title('Commission paid to wallet')->success()->send();
                    }),
            ]);
    }
}
