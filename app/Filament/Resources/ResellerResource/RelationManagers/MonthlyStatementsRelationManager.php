<?php

namespace App\Filament\Resources\ResellerResource\RelationManagers;

use App\Services\Resellers\ResellerMonthlyStatementService;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MonthlyStatementsRelationManager extends RelationManager
{
    protected static string $relationship = 'monthlyStatements';

    protected static ?string $title = 'Monthly statements';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('period_year')->label('Year'),
                Tables\Columns\TextColumn::make('period_month')->label('Month'),
                Tables\Columns\TextColumn::make('opening_admin_due')->money('BDT')->label('Opening'),
                Tables\Columns\TextColumn::make('accruals')->money('BDT'),
                Tables\Columns\TextColumn::make('collections_applied')->money('BDT')->label('Collections'),
                Tables\Columns\TextColumn::make('settlements')->money('BDT'),
                Tables\Columns\TextColumn::make('closing_admin_due')->money('BDT')->label('Closing'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('closed_at')->dateTime(),
            ])
            ->defaultSort('period_year', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('syncCurrent')
                    ->label('Sync current month')
                    ->action(function (): void {
                        $reseller = $this->getOwnerRecord();
                        app(ResellerMonthlyStatementService::class)->syncMonth(
                            $reseller,
                            (int) now()->year,
                            (int) now()->month,
                        );
                        Notification::make()->title('Statement synced')->success()->send();
                    }),
            ]);
    }
}
