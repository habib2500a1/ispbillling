<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InventoryReportPage;
use App\Models\Product;
use App\Services\Inventory\ProductConditionService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryDamagedMissingReport extends Page implements HasTable
{
    use InteractsWithTable;
    use InventoryReportPage;

    protected static ?string $slug = 'inventory-report-damaged-missing';

    protected static string $view = 'filament.pages.inventory-table-report';

    public bool $showAllProducts = false;

    public function mount(): void
    {
        $this->mountInteractsWithTable();
    }

    public function getReportTitle(): string
    {
        return 'Damaged & missing products';
    }

    public function getReportSubtitle(): string
    {
        return $this->showAllProducts
            ? 'All products — record new damaged or missing units.'
            : 'Products with damaged or missing counts greater than zero.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->showAllProducts
                ? Product::query()
                : Product::query()->where(function (Builder $q): void {
                    $q->where('damaged_qty', '>', 0)->orWhere('missing_qty', '>', 0);
                }))
            ->columns([
                Tables\Columns\TextColumn::make('sku')->fontFamily('mono'),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('stock_qty')->label('Stock'),
                Tables\Columns\TextColumn::make('damaged_qty')->label('Damaged')->color('warning'),
                Tables\Columns\TextColumn::make('missing_qty')->label('Missing')->color('danger'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('toggle_all')
                    ->label(fn (): string => $this->showAllProducts ? 'Issues only' : 'Show all products')
                    ->action(fn () => $this->showAllProducts = ! $this->showAllProducts),
            ])
            ->actions([
                Tables\Actions\Action::make('record_condition')
                    ->label('Record')
                    ->icon('heroicon-o-exclamation-circle')
                    ->form([
                        Forms\Components\Select::make('kind')
                            ->options(['damaged' => 'Damaged', 'missing' => 'Missing'])
                            ->required(),
                        Forms\Components\TextInput::make('quantity')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                        Forms\Components\Toggle::make('reduce_stock')
                            ->label('Reduce sellable stock')
                            ->default(true),
                        Forms\Components\Textarea::make('notes')->rows(2),
                    ])
                    ->action(function (Product $record, array $data): void {
                        app(ProductConditionService::class)->record(
                            $record,
                            (string) $data['kind'],
                            (int) $data['quantity'],
                            (bool) ($data['reduce_stock'] ?? true),
                            auth()->user(),
                            $data['notes'] ?? null,
                        );
                        Notification::make()->title('Recorded')->success()->send();
                    }),
            ]);
    }
}
