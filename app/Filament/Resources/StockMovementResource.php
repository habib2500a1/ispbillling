<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksIspPermission;
use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\StockMovement;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StockMovementResource extends Resource
{
    use ChecksIspPermission;

    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Inventory Pro';

    protected static ?string $navigationLabel = 'Stock ledger';

    protected static ?int $navigationSort = 5;

    protected static bool $shouldRegisterNavigation = false;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('moved_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('warehouse.code')->label('WH')->placeholder('—'),
                Tables\Columns\TextColumn::make('product.name')->searchable(),
                Tables\Columns\TextColumn::make('product.sku')->label('SKU'),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('quantity')
                    ->formatStateUsing(fn (int $state): string => ($state > 0 ? '+' : '').$state)
                    ->color(fn (int $state): string => $state >= 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('stock_before')->label('Before'),
                Tables\Columns\TextColumn::make('stock_after')->label('After'),
                Tables\Columns\TextColumn::make('unit_cost')->money('BDT'),
                Tables\Columns\TextColumn::make('notes')->limit(40),
                Tables\Columns\TextColumn::make('recorder.name')->label('By'),
            ])
            ->defaultSort('moved_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        StockMovement::TYPE_PURCHASE => 'Purchase receive',
                        StockMovement::TYPE_SALE => 'Sale',
                        StockMovement::TYPE_ADJUSTMENT_IN => 'Adjustment in',
                        StockMovement::TYPE_ADJUSTMENT_OUT => 'Adjustment out',
                        StockMovement::TYPE_TRANSFER_IN => 'Transfer in',
                        StockMovement::TYPE_TRANSFER_OUT => 'Transfer out',
                    ]),
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->relationship('warehouse', 'name'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
        ];
    }

    protected static function permissionPrefix(): string
    {
        return 'inventory';
    }
}
