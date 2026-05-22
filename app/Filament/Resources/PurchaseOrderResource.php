<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ChecksIspPermission;
use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Filament\Support\InventoryWarehouseSelect;
use App\Services\Inventory\InventoryAccountingService;
use App\Services\Inventory\InventoryStockService;
use Filament\Notifications\Notification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseOrderResource extends Resource
{
    use ChecksIspPermission;

    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Inventory Pro';

    protected static ?string $navigationLabel = 'Purchase orders';

    protected static ?int $navigationSort = 3;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('po_number')
                ->label('PO number')
                ->default(fn () => 'PO-'.now()->format('Ymd-His'))
                ->required(),
            InventoryWarehouseSelect::make(),
            Forms\Components\Select::make('vendor_id')
                ->options(fn () => Vendor::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->nullable()
                ->native(false),
            Forms\Components\Select::make('status')
                ->options([
                    'draft' => 'Draft',
                    'ordered' => 'Ordered',
                    'received' => 'Received',
                    'cancelled' => 'Cancelled',
                ])
                ->default('draft')
                ->native(false),
            Forms\Components\DatePicker::make('ordered_at'),
            Forms\Components\DatePicker::make('received_at'),
            Forms\Components\Repeater::make('items')
                ->relationship()
                ->schema([
                    Forms\Components\Select::make('product_id')
                        ->options(fn () => Product::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            if ($state) {
                                $p = Product::find($state);
                                if ($p) {
                                    $set('unit_price', $p->cost_price > 0 ? $p->cost_price : $p->unit_price);
                                    $set('description', $p->name);
                                }
                            }
                        }),
                    Forms\Components\TextInput::make('description'),
                    Forms\Components\TextInput::make('quantity')->numeric()->default(1)->integer()->live(),
                    Forms\Components\TextInput::make('unit_price')->numeric()->default(0)->live(),
                    Forms\Components\TextInput::make('line_total')
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->afterStateHydrated(function (Forms\Components\TextInput $component, $state, $get): void {
                            $qty = (int) ($get('quantity') ?? 1);
                            $price = (float) ($get('unit_price') ?? 0);
                            $component->state($qty * $price);
                        }),
                ])
                ->columns(5)
                ->columnSpanFull(),
            Forms\Components\Textarea::make('notes')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('po_number')->searchable()->fontFamily('mono'),
            Tables\Columns\TextColumn::make('vendor.name')->label('Vendor'),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('total')->money('BDT'),
            Tables\Columns\TextColumn::make('ordered_at')->date(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('mark_received')
                ->label('Received')
                ->icon('heroicon-o-check')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (PurchaseOrder $record): bool => ! in_array($record->status, ['received', 'cancelled'], true))
                ->action(function (PurchaseOrder $record): void {
                    $record->load('items');
                    app(InventoryStockService::class)->receivePurchaseOrder($record, auth()->user());
                    app(InventoryAccountingService::class)->postPurchaseReceive($record->fresh());

                    Notification::make()
                        ->title('Stock received')
                        ->body('Inventory updated · accounting posted (inventory + AP).')
                        ->success()
                        ->send();
                }),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }

    protected static function permissionPrefix(): string
    {
        return 'inventory';
    }
}
