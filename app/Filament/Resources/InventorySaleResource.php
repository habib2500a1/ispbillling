<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksIspPermission;
use App\Filament\Forms\InventorySaleFormSchema;
use App\Filament\Resources\InventorySaleResource\Pages;
use App\Models\InventorySale;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InventorySaleResource extends Resource
{
    use ChecksIspPermission;

    protected static ?string $model = InventorySale::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Inventory Pro';

    protected static ?string $navigationLabel = 'Retail sales';

    protected static ?int $navigationSort = 4;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return InventorySaleFormSchema::configure($form);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('sale_number')->searchable()->fontFamily('mono'),
            Tables\Columns\TextColumn::make('warehouse.code')->label('Warehouse')->fontFamily('mono')->placeholder('—'),
            Tables\Columns\TextColumn::make('sold_at')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('channel')->badge(),
            Tables\Columns\TextColumn::make('customer_name')->placeholder('Walk-in'),
            Tables\Columns\TextColumn::make('total')->money('BDT')->sortable(),
            Tables\Columns\TextColumn::make('total_cost')->label('COGS')->money('BDT'),
            Tables\Columns\TextColumn::make('gross_profit')->label('Profit')->money('BDT')->color('success'),
            Tables\Columns\TextColumn::make('payment_method'),
        ])
            ->defaultSort('sold_at', 'desc')
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('print_receipt')
                        ->label('Thermal print')
                        ->icon('heroicon-o-printer')
                        ->url(fn (InventorySale $record): string => route('inventory-sales.receipt', $record).'?print=1')
                        ->openUrlInNewTab(),
                    Tables\Actions\Action::make('pdf_receipt')
                        ->label('PDF (A4)')
                        ->icon('heroicon-o-document-arrow-down')
                        ->url(fn (InventorySale $record): string => route('inventory-sales.receipt.pdf', $record))
                        ->openUrlInNewTab(),
                ])
                    ->icon('heroicon-m-printer')
                    ->color('gray')
                    ->tooltip('Receipts'),
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventorySales::route('/'),
            'create' => Pages\CreateInventorySale::route('/create'),
            'view' => Pages\ViewInventorySale::route('/{record}'),
        ];
    }

    protected static function permissionPrefix(): string
    {
        return 'inventory';
    }
}
