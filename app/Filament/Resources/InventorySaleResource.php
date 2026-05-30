<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksIspPermission;
use App\Filament\Resources\InventorySaleResource\Pages;
use App\Models\InventorySale;
use App\Models\Product;
use App\Filament\Support\InventoryWarehouseSelect;
use App\Services\Inventory\ProductBarcodeLookup;
use App\Services\Inventory\WarehouseResolver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
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
        return $form->schema([
            Forms\Components\Section::make('Sale')
                ->schema([
                    Forms\Components\TextInput::make('sale_number')
                        ->default(fn () => InventorySale::generateSaleNumber(\App\Support\TenantResolver::requiredTenantId()))
                        ->disabled()
                        ->dehydrated(),
                    Forms\Components\Select::make('channel')
                        ->options([
                            'counter' => 'Counter / desk',
                            'shop' => 'Public shop',
                            'field' => 'Field',
                        ])
                        ->default('counter')
                        ->native(false),
                    Forms\Components\Select::make('payment_method')
                        ->options([
                            'cash' => 'Cash',
                            'bkash' => 'bKash',
                            'nagad' => 'Nagad',
                            'bank' => 'Bank',
                        ])
                        ->default('cash')
                        ->native(false),
                    Forms\Components\TextInput::make('customer_name'),
                    Forms\Components\TextInput::make('customer_phone')->tel(),
                    Forms\Components\TextInput::make('discount')->numeric()->default(0)->live(),
                    InventoryWarehouseSelect::make(),
                ])->columns(2),
            Forms\Components\Section::make('Barcode scan')
                ->schema([
                    Forms\Components\TextInput::make('barcode_scan')
                        ->label('Scan barcode / SKU')
                        ->placeholder('Scan or type — adds one line on save')
                        ->helperText('USB scanner: focus here, scan, then add qty in lines or save.'),
                ]),
            Forms\Components\Section::make('Items')
                ->schema([
                    Forms\Components\Repeater::make('lines')
                        ->schema([
                            Forms\Components\Select::make('product_id')
                                ->label('Product')
                                ->options(function (Get $get): array {
                                    $tenantId = \App\Support\TenantResolver::requiredTenantId();
                                    $warehouseId = app(WarehouseResolver::class)->resolveWarehouseId(
                                        $tenantId,
                                        $get('../../warehouse_id') ? (int) $get('../../warehouse_id') : null,
                                    );

                                    return Product::query()
                                        ->where('is_active', true)
                                        ->orderBy('name')
                                        ->get()
                                        ->filter(function (Product $p) use ($warehouseId): bool {
                                            return app(WarehouseResolver::class)->stockAt($p, $warehouseId) > 0
                                                || (int) $p->stock_qty > 0;
                                        })
                                        ->mapWithKeys(function (Product $p) use ($warehouseId): array {
                                            $whStock = app(WarehouseResolver::class)->stockAt($p, $warehouseId);

                                            return [
                                                $p->id => $p->name
                                                    .($p->barcode ? ' ['.$p->barcode.']' : '')
                                                    .' · wh '.$whStock
                                                    .' · sell '.number_format($p->effectiveSellPrice(), 0),
                                            ];
                                        })
                                        ->all();
                                })
                                ->searchable()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set): void {
                                    if ($state) {
                                        $p = Product::find($state);
                                        if ($p) {
                                            $set('unit_price', $p->effectiveSellPrice());
                                        }
                                    }
                                }),
                            Forms\Components\TextInput::make('barcode_quick')
                                ->label('Barcode')
                                ->live(debounce: 400)
                                ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                                    if (! $state) {
                                        return;
                                    }
                                    $tenantId = \App\Support\TenantResolver::requiredTenantId();
                                    $p = app(ProductBarcodeLookup::class)->find($tenantId, $state);
                                    if ($p) {
                                        $set('product_id', $p->id);
                                        $set('unit_price', $p->effectiveSellPrice());
                                    }
                                }),
                            Forms\Components\TextInput::make('quantity')->numeric()->integer()->default(1)->minValue(1)->required()->live(),
                            Forms\Components\TextInput::make('unit_price')->numeric()->required()->live(),
                            Forms\Components\Placeholder::make('line_total')
                                ->content(function (Get $get): string {
                                    $qty = (int) ($get('quantity') ?? 1);
                                    $price = (float) ($get('unit_price') ?? 0);

                                    return number_format($qty * $price, 2).' BDT';
                                }),
                        ])
                        ->columns(4)
                        ->minItems(1)
                        ->columnSpanFull(),
                ]),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('sale_number')->searchable()->fontFamily('mono'),
            Tables\Columns\TextColumn::make('sold_at')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('channel')->badge(),
            Tables\Columns\TextColumn::make('customer_name')->placeholder('Walk-in'),
            Tables\Columns\TextColumn::make('total')->money('BDT')->sortable(),
            Tables\Columns\TextColumn::make('total_cost')->label('COGS')->money('BDT'),
            Tables\Columns\TextColumn::make('gross_profit')->label('Profit')->money('BDT')->color('success'),
            Tables\Columns\TextColumn::make('payment_method'),
        ])
            ->defaultSort('sold_at', 'desc')
            ->actions([Tables\Actions\ViewAction::make()]);
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
