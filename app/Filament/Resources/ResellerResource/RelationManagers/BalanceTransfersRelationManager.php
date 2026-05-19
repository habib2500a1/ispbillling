<?php

namespace App\Filament\Resources\ResellerResource\RelationManagers;

use App\Models\ResellerBalanceTransfer;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class BalanceTransfersRelationManager extends RelationManager
{
    protected static string $relationship = 'balanceTransfersIn';

    protected static ?string $title = 'Balance transfers';

    protected static ?string $icon = 'heroicon-o-arrows-right-left';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('fromReseller.name')->label('From')->placeholder('HQ / system'),
                Tables\Columns\TextColumn::make('amount')->money('BDT'),
                Tables\Columns\TextColumn::make('transfer_type')
                    ->formatStateUsing(fn (string $s): string => ResellerBalanceTransfer::typeLabel($s)),
                Tables\Columns\TextColumn::make('reference')->fontFamily('mono'),
                Tables\Columns\TextColumn::make('notes')->limit(40),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
