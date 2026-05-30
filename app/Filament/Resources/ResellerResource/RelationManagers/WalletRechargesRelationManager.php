<?php

namespace App\Filament\Resources\ResellerResource\RelationManagers;

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
                Tables\Columns\TextColumn::make('amount')->money('BDT'),
                Tables\Columns\TextColumn::make('payment_method')->label('Method')->badge(),
                Tables\Columns\TextColumn::make('reference')->label('Trx / ref')->limit(24),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
                Tables\Columns\TextColumn::make('reviewed_at')->dateTime(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ResellerWalletRechargeRequest $record): bool => $record->status === ResellerWalletRechargeRequest::STATUS_PENDING)
                    ->requiresConfirmation()
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
