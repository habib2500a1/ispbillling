<?php

namespace App\Filament\Forms;

use App\Filament\Support\InventoryWarehouseSelect;
use App\Filament\Support\PosProductOptions;
use App\Models\InventorySale;
use App\Models\Product;
use App\Services\Inventory\ProductBarcodeLookup;
use App\Services\Inventory\WarehouseResolver;
use App\Support\TenantResolver;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;

final class InventorySaleFormSchema
{
    public static function configure(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Sale')
                ->schema([
                    Forms\Components\TextInput::make('sale_number')
                        ->default(fn () => InventorySale::generateSaleNumber(TenantResolver::requiredTenantId()))
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
                    InventoryWarehouseSelect::make()
                        ->live(),
                ])
                ->columns(2),
            Forms\Components\Section::make('Quick pick')
                ->description('Tap a product photo to add a line (tap again to increase qty).')
                ->schema([
                    Forms\Components\TextInput::make('pos_quick_search')
                        ->label('Search products')
                        ->placeholder('Name, SKU, barcode…')
                        ->dehydrated(false)
                        ->live(debounce: 300),
                    Forms\Components\ViewField::make('pos_quick_grid')
                        ->view('filament.forms.components.pos-product-grid')
                        ->viewData(fn (Get $get): array => [
                            'products' => PosProductOptions::gridItems(
                                $get('warehouse_id') ? (int) $get('warehouse_id') : null,
                                (string) ($get('pos_quick_search') ?? ''),
                            ),
                        ])
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed(false),
            Forms\Components\Section::make('Barcode scan')
                ->description('Scan adds a line immediately (same product increases qty).')
                ->schema([
                    Forms\Components\TextInput::make('barcode_scan')
                        ->label('Scan barcode / SKU')
                        ->placeholder('Focus here and scan…')
                        ->autofocus()
                        ->live(debounce: 350)
                        ->afterStateUpdated(function (?string $state, $livewire): void {
                            if (is_object($livewire) && method_exists($livewire, 'appendScannedBarcode')) {
                                $livewire->appendScannedBarcode($state);
                            }
                        }),
                ]),
            Forms\Components\Section::make('Line items')
                ->schema([
                    Forms\Components\Repeater::make('lines')
                        ->schema([
                            Forms\Components\ViewField::make('product_thumb')
                                ->view('filament.forms.components.pos-product-thumb')
                                ->viewData(fn (Get $get): array => [
                                    'productId' => $get('product_id'),
                                ])
                                ->columnSpan(1),
                            Forms\Components\Select::make('product_id')
                                ->label('Product')
                                ->options(fn (Get $get): array => PosProductOptions::labels(
                                    $get('../../warehouse_id') ? (int) $get('../../warehouse_id') : null,
                                ))
                                ->allowHtml()
                                ->searchable()
                                ->required()
                                ->live()
                                ->columnSpan(2)
                                ->afterStateUpdated(function ($state, Set $set): void {
                                    if ($state) {
                                        $product = Product::find($state);
                                        if ($product) {
                                            $set('unit_price', $product->effectiveSellPrice());
                                        }
                                    }
                                }),
                            Forms\Components\TextInput::make('barcode_quick')
                                ->label('Barcode')
                                ->live(debounce: 400)
                                ->columnSpan(1)
                                ->afterStateUpdated(function (?string $state, Set $set): void {
                                    if (! $state) {
                                        return;
                                    }
                                    $product = app(ProductBarcodeLookup::class)->find(
                                        TenantResolver::requiredTenantId(),
                                        $state,
                                    );
                                    if ($product) {
                                        $set('product_id', $product->id);
                                        $set('unit_price', $product->effectiveSellPrice());
                                    }
                                }),
                            Forms\Components\TextInput::make('quantity')
                                ->numeric()
                                ->integer()
                                ->default(1)
                                ->minValue(1)
                                ->required()
                                ->live()
                                ->columnSpan(1),
                            Forms\Components\TextInput::make('unit_price')
                                ->label('Unit price')
                                ->numeric()
                                ->required()
                                ->live()
                                ->columnSpan(1),
                            Forms\Components\Placeholder::make('line_total')
                                ->label('Line total')
                                ->content(function (Get $get): string {
                                    $qty = (int) ($get('quantity') ?? 1);
                                    $price = (float) ($get('unit_price') ?? 0);

                                    return number_format($qty * $price, 2).' BDT';
                                })
                                ->columnSpan(1),
                        ])
                        ->columns(7)
                        ->minItems(1)
                        ->columnSpanFull()
                        ->addActionLabel('Add line'),
                ]),
            Forms\Components\Placeholder::make('sale_total_preview')
                ->label('Estimated total')
                ->content(function (Get $get): string {
                    $subtotal = 0.0;
                    foreach ($get('lines') ?? [] as $line) {
                        $subtotal += (int) ($line['quantity'] ?? 1) * (float) ($line['unit_price'] ?? 0);
                    }
                    $discount = (float) ($get('discount') ?? 0);

                    return number_format(max(0, $subtotal - $discount), 2).' BDT (before save)';
                })
                ->columnSpanFull(),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ]);
    }
}
