<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksIspPermission;
use App\Filament\Resources\WarehouseResource\Pages;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryStockService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WarehouseResource extends Resource
{
    use ChecksIspPermission;

    protected static ?string $model = Warehouse::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationGroup = 'Inventory Pro';

    protected static ?string $navigationLabel = 'Warehouses';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->required()
                ->maxLength(32)
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\Textarea::make('address')->columnSpanFull(),
            Forms\Components\Toggle::make('is_default')
                ->label('Default warehouse')
                ->helperText('New PO/sales use this when none selected.'),
            Forms\Components\Toggle::make('is_active')->default(true),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->fontFamily('mono')->searchable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\IconColumn::make('is_default')->label('Default')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('stock_levels_count')
                    ->label('SKU rows')
                    ->counts('stockLevels'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('transfer_stock')
                    ->label('Transfer stock')
                    ->icon('heroicon-o-arrows-right-left')
                    ->form([
                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->options(fn () => Product::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->native(false),
                        Forms\Components\Select::make('to_warehouse_id')
                            ->label('To warehouse')
                            ->options(fn (Warehouse $record) => Warehouse::query()
                                ->where('is_active', true)
                                ->whereKeyNot($record->id)
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (Warehouse $w) => [$w->id => $w->displayLabel()]))
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('quantity')->numeric()->integer()->minValue(1)->required(),
                        Forms\Components\Textarea::make('notes'),
                    ])
                    ->action(function (Warehouse $record, array $data): void {
                        $product = Product::findOrFail($data['product_id']);
                        app(InventoryStockService::class)->transfer(
                            $product,
                            (int) $record->id,
                            (int) $data['to_warehouse_id'],
                            (int) $data['quantity'],
                            $data['notes'] ?? null,
                            auth()->user(),
                        );
                        Notification::make()->title('Stock transferred')->success()->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'edit' => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }

    protected static function permissionPrefix(): string
    {
        return 'inventory';
    }
}
