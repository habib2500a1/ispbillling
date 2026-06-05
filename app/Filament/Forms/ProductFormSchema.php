<?php

namespace App\Filament\Forms;

use App\Filament\Support\InventoryWarehouseSelect;
use App\Models\Product;
use App\Support\TenantResolver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;

final class ProductFormSchema
{
    public static function configure(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Product image')
                ->description('Shown on the public shop and product list. JPG/PNG/WebP, max 5 MB.')
                ->schema([
                    Forms\Components\FileUpload::make('image_path')
                        ->label('Photo')
                        ->image()
                        ->disk('public')
                        ->directory(fn (?Product $record): string => 'products/'.TenantResolver::requiredTenantId().'/'
                            .($record?->getKey() ?? 'draft'))
                        ->visibility('public')
                        ->maxSize(5120)
                        ->imageEditor()
                        ->imageEditorAspectRatios([
                            '16:9',
                            '4:3',
                            '1:1',
                        ])
                        ->columnSpanFull(),
                ]),
            Forms\Components\Section::make('Product details')
                ->description('Scan barcode first, then name. SKU is optional — auto-generated if empty.')
                ->schema([
                    Forms\Components\TextInput::make('barcode')
                        ->label('Barcode / EAN')
                        ->maxLength(64)
                        ->autofocus()
                        ->helperText('USB scanner: focus here and scan.'),
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true),
                    Forms\Components\TextInput::make('sku')
                        ->label('SKU')
                        ->maxLength(64)
                        ->unique(ignoreRecord: true)
                        ->helperText('Leave blank to auto-generate on save.'),
                    Forms\Components\TextInput::make('unit')
                        ->default('pcs')
                        ->maxLength(32),
                    Forms\Components\Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Forms\Components\Section::make('Pricing')
                ->description('Buy price (cost), sell price, and margin per unit.')
                ->schema([
                    Forms\Components\TextInput::make('cost_price')
                        ->label('Buy / cost (BDT)')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->live(),
                    Forms\Components\TextInput::make('sell_price')
                        ->label('Sell price (BDT)')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->live(),
                    Forms\Components\Placeholder::make('margin_hint')
                        ->label('Unit profit')
                        ->content(function (?Product $record, Get $get): string {
                            $cost = (float) ($get('cost_price') ?? $record?->cost_price ?? 0);
                            $sell = (float) ($get('sell_price') ?? $record?->sell_price ?? 0);

                            return number_format(max(0, $sell - $cost), 2).' BDT';
                        }),
                    Forms\Components\TextInput::make('unit_price')
                        ->label('Legacy unit price')
                        ->helperText('Fallback for old data; synced from sell price on create.')
                        ->numeric()
                        ->default(0)
                        ->visible(fn (string $operation): bool => $operation === 'edit'),
                ])
                ->columns(2),
            Forms\Components\Section::make('Opening stock')
                ->description('Optional — receive stock into your default warehouse when the product is created.')
                ->schema([
                    InventoryWarehouseSelect::make('opening_warehouse_id')
                        ->label('Warehouse')
                        ->dehydrated(false),
                    Forms\Components\TextInput::make('opening_stock_qty')
                        ->label('Quantity')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->default(0)
                        ->dehydrated(false)
                        ->helperText('0 = product only, no stock movement.'),
                ])
                ->columns(2)
                ->visible(fn (string $operation): bool => $operation === 'create'),
            Forms\Components\Section::make('Stock & visibility')
                ->schema([
                    Forms\Components\TextInput::make('stock_qty')
                        ->label('Total stock (all warehouses)')
                        ->numeric()
                        ->integer()
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (string $operation): bool => $operation === 'edit'),
                    Forms\Components\TextInput::make('reorder_level')
                        ->numeric()
                        ->default(0)
                        ->integer()
                        ->minValue(0)
                        ->helperText('Alert when stock is at or below this level.'),
                    Forms\Components\TextInput::make('damaged_qty')
                        ->label('Damaged units')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->default(0),
                    Forms\Components\TextInput::make('missing_qty')
                        ->label('Missing units')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->default(0),
                    Forms\Components\TextInput::make('last_purchase_cost')
                        ->label('Last purchase cost')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (string $operation): bool => $operation === 'edit'),
                    Forms\Components\Toggle::make('is_active')
                        ->default(true),
                    Forms\Components\Toggle::make('show_on_shop')
                        ->label('Show on public shop')
                        ->default(false)
                        ->helperText('Requires stock and sell price for storefront.'),
                    Forms\Components\Textarea::make('notes')
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }
}
