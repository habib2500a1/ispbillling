<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use App\Filament\Support\InventoryWarehouseSelect;
use App\Models\Device;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Services\Inventory\InvoiceHardwareLineService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Line items';

    protected static ?string $icon = 'heroicon-o-list-bullet';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('item_type')
                    ->options([
                        'package' => 'Package / service',
                        'addon' => 'Add-on',
                        'onu_lease' => 'ONU lease',
                        'hardware' => 'Hardware / product (catalog)',
                        'product' => 'Product charge',
                        'late_fee' => 'Late fee',
                        'discount' => 'Discount line',
                        'custom' => 'Custom charge',
                        'line' => 'Other',
                    ])
                    ->default('custom')
                    ->required()
                    ->live()
                    ->native(false),
                Forms\Components\Select::make('product_id')
                    ->label('Catalog product')
                    ->options(fn () => Product::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->visible(fn (Get $get): bool => in_array($get('item_type'), ['hardware', 'product'], true))
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set): void {
                        if ($state) {
                            $p = Product::find($state);
                            if ($p) {
                                $set('description', $p->name);
                                $set('unit_price', $p->effectiveSellPrice());
                            }
                        }
                    }),
                Forms\Components\Select::make('device_id')
                    ->label('Network device (ONU/CPE)')
                    ->options(function (): array {
                        $customerId = $this->getOwnerRecord()->customer_id;

                        return Device::query()
                            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
                            ->orderBy('serial_number')
                            ->get()
                            ->mapWithKeys(fn (Device $d) => [
                                $d->id => ($d->display_name ?: $d->serial_number).' · '.$d->type,
                            ])
                            ->all();
                    })
                    ->searchable()
                    ->visible(fn (Get $get): bool => $get('item_type') === 'onu_lease'),
                InventoryWarehouseSelect::make()
                    ->visible(fn (Get $get): bool => in_array($get('item_type'), ['hardware', 'product'], true)),
                Forms\Components\Toggle::make('issue_stock')
                    ->label('Issue stock from warehouse')
                    ->default(false)
                    ->visible(fn (Get $get): bool => in_array($get('item_type'), ['hardware', 'product'], true) && (bool) $get('product_id')),
                Forms\Components\TextInput::make('description')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('quantity')
                    ->numeric()
                    ->default(1)
                    ->required()
                    ->minValue(0.01),
                Forms\Components\TextInput::make('unit_price')
                    ->label('Unit price (BDT)')
                    ->numeric()
                    ->required()
                    ->prefix('৳'),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('item_type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', $state)),
                Tables\Columns\TextColumn::make('description')
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('device.serial_number')
                    ->label('Device')
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('stock_issued')
                    ->label('Stock out')
                    ->boolean(),
                Tables\Columns\TextColumn::make('quantity')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('unit_price')
                    ->money('BDT'),
                Tables\Columns\TextColumn::make('line_total')
                    ->label('Total')
                    ->money('BDT')
                    ->weight('bold'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        if (! empty($data['product_id']) && in_array($data['item_type'] ?? '', ['hardware', 'product'], true)) {
                            $data['meta'] = array_merge($data['meta'] ?? [], [
                                'product_id' => (int) $data['product_id'],
                                'warehouse_id' => $data['warehouse_id'] ?? null,
                            ]);
                        }
                        if (! empty($data['device_id'])) {
                            $data['meta'] = array_merge($data['meta'] ?? [], ['device_id' => (int) $data['device_id']]);
                        }

                        return $data;
                    })
                    ->after(function (InvoiceItem $record, array $data): void {
                        if (! empty($data['issue_stock']) && $record->product_id && ! $record->stock_issued) {
                            app(InvoiceHardwareLineService::class)->issueStockForItem($record, auth()->user());
                            Notification::make()->title('Stock issued for line')->success()->send();
                        }
                    }),
                Tables\Actions\Action::make('add_hardware')
                    ->label('Add hardware line')
                    ->icon('heroicon-o-cpu-chip')
                    ->form([
                        Forms\Components\Select::make('product_id')
                            ->label('Product')
                            ->options(fn () => Product::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('quantity')->numeric()->integer()->default(1)->minValue(1)->required(),
                        Forms\Components\TextInput::make('unit_price')->numeric()->label('Unit price (BDT)'),
                        InventoryWarehouseSelect::make(),
                        Forms\Components\Toggle::make('issue_stock')->label('Issue stock now')->default(true),
                    ])
                    ->action(function (array $data): void {
                        $invoice = $this->getOwnerRecord();
                        $product = Product::findOrFail($data['product_id']);
                        app(InvoiceHardwareLineService::class)->addProductLine(
                            $invoice,
                            $product,
                            (int) $data['quantity'],
                            isset($data['unit_price']) ? (float) $data['unit_price'] : null,
                            isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null,
                            (bool) ($data['issue_stock'] ?? false),
                            auth()->user(),
                        );
                        Notification::make()->title('Hardware line added')->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('issue_stock')
                    ->label('Issue stock')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (InvoiceItem $record): bool => $record->product_id && ! $record->stock_issued)
                    ->requiresConfirmation()
                    ->action(function (InvoiceItem $record): void {
                        app(InvoiceHardwareLineService::class)->issueStockForItem($record, auth()->user());
                        Notification::make()->title('Stock issued')->success()->send();
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
