<?php

namespace App\Filament\Resources\ResellerResource\RelationManagers;

use App\Models\ResellerSettlement;
use App\Services\Resellers\ResellerSettlementService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SettlementsRelationManager extends RelationManager
{
    protected static string $relationship = 'settlements';

    protected static ?string $title = 'Settlements';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('amount')->numeric()->required()->prefix('BDT'),
            Forms\Components\TextInput::make('expense_deduction')->numeric()->default(0)->prefix('BDT'),
            Forms\Components\Textarea::make('notes')->rows(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('settlement_number')
            ->columns([
                Tables\Columns\TextColumn::make('settlement_number')->searchable(),
                Tables\Columns\TextColumn::make('amount')->money('BDT'),
                Tables\Columns\TextColumn::make('expense_deduction')->money('BDT')->label('Expenses'),
                Tables\Columns\TextColumn::make('net_amount')->money('BDT')->label('Net'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime(),
                Tables\Columns\TextColumn::make('approved_at')->dateTime(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ResellerSettlement $record): bool => $record->status === ResellerSettlement::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->action(function (ResellerSettlement $record): void {
                        try {
                            app(ResellerSettlementService::class)->approve($record, auth()->user());
                            Notification::make()->title('Settlement approved')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Could not approve')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ResellerSettlement $record): bool => $record->status === ResellerSettlement::STATUS_PENDING)
                    ->form([
                        Forms\Components\Textarea::make('reason')->required()->label('Rejection reason'),
                    ])
                    ->action(function (ResellerSettlement $record, array $data): void {
                        app(ResellerSettlementService::class)->reject($record, auth()->user(), $data['reason']);
                        Notification::make()->title('Settlement rejected')->success()->send();
                    }),
            ]);
    }
}
