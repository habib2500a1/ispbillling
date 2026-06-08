<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksIspPermission;
use App\Filament\Forms\ProductFormSchema;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
        return ProductFormSchema::configure($form);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Photo')
                    ->disk('public')
                    ->visibility('public')
                    ->height(52)
                    ->width(52)
                    ->extraImgAttributes(['class' => 'iv-product-thumb'])
                    ->defaultImageUrl(fn (Product $record): string => 'https://ui-avatars.com/api/?name='
                        .urlencode(mb_substr((string) $record->name, 0, 2))
                        .'&background=f97316&color=fff&size=128'),
                Tables\Columns\TextColumn::make('sku')->fontFamily('mono')->searchable(),
                Tables\Columns\TextColumn::make('barcode')->fontFamily('mono')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('name')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('stock_qty')
                    ->label('Stock')
                    ->sortable()
                    ->color(fn (Product $record): string => $record->isLowStock() ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('warehouse_breakdown')
                    ->label('By warehouse')
                    ->state(function (Product $record): string {
                        return $record->warehouseStocks()
                            ->with('warehouse')
                            ->get()
                            ->filter(fn ($row) => (int) $row->stock_qty > 0)
                            ->map(fn ($row) => ($row->warehouse?->code ?? '?').': '.(int) $row->stock_qty)
                            ->join(' · ') ?: '—';
                    })
                    ->wrap()
                    ->toggleable(),
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
            ->filters([
                TernaryFilter::make('show_on_shop')
                    ->label('Public shop')
                    ->placeholder('All'),
                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All'),
                Tables\Filters\Filter::make('low_stock')
                    ->label('Low stock')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('reorder_level', '>', 0)
                        ->whereColumn('stock_qty', '<=', 'reorder_level')),
                Tables\Filters\Filter::make('has_image')
                    ->label('Has photo')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('image_path')->where('image_path', '!=', '')),
            ])
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->persistFiltersInSession(false)
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
