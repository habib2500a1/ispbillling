<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksIspPermission;
use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Filament\Support\InventoryWarehouseSelect;
use App\Services\Inventory\InventoryStockService;
use App\Models\StockMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    use ChecksIspPermission;

    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Inventory Pro';

    protected static ?string $navigationLabel = 'Products';

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Product')
                ->schema([
                    Forms\Components\TextInput::make('sku')->maxLength(64)->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('barcode')
                        ->label('Barcode / EAN')
                        ->maxLength(64)
                        ->helperText('Scan at POS or search by barcode.'),
                    Forms\Components\TextInput::make('name')->required(),
                    Forms\Components\Textarea::make('description')->rows(3)->columnSpanFull(),
                    Forms\Components\TextInput::make('unit')->default('pcs'),
                ])->columns(2),
            Forms\Components\Section::make('Pricing & margin')
                ->description('Buy price (cost), sell price, and profit per unit.')
                ->schema([
                    Forms\Components\TextInput::make('cost_price')
                        ->label('Buy / cost price (BDT)')
                        ->numeric()
                        ->default(0)
                        ->live(),
                    Forms\Components\TextInput::make('sell_price')
                        ->label('Sell price (BDT)')
                        ->numeric()
                        ->default(0)
                        ->live(),
                    Forms\Components\Placeholder::make('margin_hint')
                        ->label('Unit profit')
                        ->content(function (?Product $record, Forms\Get $get): string {
                            $cost = (float) ($get('cost_price') ?? $record?->cost_price ?? 0);
                            $sell = (float) ($get('sell_price') ?? $record?->sell_price ?? 0);

                            return number_format(max(0, $sell - $cost), 2).' BDT';
                        }),
                    Forms\Components\TextInput::make('unit_price')
                        ->label('Legacy unit price')
                        ->helperText('Used as fallback if cost/sell empty; PO default buy price.')
                        ->numeric()
                        ->default(0),
                    Forms\Components\TextInput::make('last_purchase_cost')
                        ->label('Last purchase cost')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(false),
                ])->columns(2),
            Forms\Components\Section::make('Stock')
                ->schema([
                    Forms\Components\TextInput::make('stock_qty')
                        ->label('Stock on hand')
                        ->numeric()
                        ->integer()
                        ->default(0)
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('reorder_level')
                        ->numeric()
                        ->default(0)
                        ->integer(),
                    Forms\Components\Toggle::make('is_active')->default(true),
                    Forms\Components\Toggle::make('show_on_shop')
                        ->label('Show on public shop')
                        ->default(false),
                    Forms\Components\Textarea::make('notes')->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')->fontFamily('mono')->searchable(),
                Tables\Columns\TextColumn::make('barcode')->fontFamily('mono')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('name')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('stock_qty')
                    ->label('Stock')
                    ->sortable()
                    ->color(fn (Product $record): string => $record->isLowStock() ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('cost_price')->label('Buy')->money('BDT'),
                Tables\Columns\TextColumn::make('sell_price')->label('Sell')->money('BDT'),
                Tables\Columns\TextColumn::make('margin')
                    ->label('Profit/u')
                    ->state(fn (Product $record): float => $record->marginPerUnit())
                    ->money('BDT')
                    ->color('success'),
                Tables\Columns\IconColumn::make('show_on_shop')->label('Shop')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('adjust_stock')
                    ->label('Adjust stock')
                    ->icon('heroicon-o-arrows-up-down')
                    ->form([
                        InventoryWarehouseSelect::make(),
                        Forms\Components\Select::make('direction')
                            ->options(['in' => 'Add stock', 'out' => 'Remove stock'])
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('quantity')->numeric()->integer()->minValue(1)->required(),
                        Forms\Components\TextInput::make('unit_cost')->label('Unit cost (BDT)')->numeric()->default(0),
                        Forms\Components\Textarea::make('notes'),
                    ])
                    ->action(function (Product $record, array $data): void {
                        $qty = (int) $data['quantity'];
                        $delta = $data['direction'] === 'in' ? $qty : -$qty;
                        $type = $data['direction'] === 'in'
                            ? StockMovement::TYPE_ADJUSTMENT_IN
                            : StockMovement::TYPE_ADJUSTMENT_OUT;

                        app(InventoryStockService::class)->adjustStock(
                            $record,
                            $delta,
                            $type,
                            (float) ($data['unit_cost'] ?? $record->effectiveCost()),
                            $record->effectiveSellPrice(),
                            null,
                            null,
                            $data['notes'] ?? 'Manual adjustment',
                            auth()->user(),
                            isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null,
                        );

                        Notification::make()->title('Stock updated')->success()->send();
                    }),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    protected static function permissionPrefix(): string
    {
        return 'inventory';
    }
}
