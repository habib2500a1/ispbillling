<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\InventoryReportPage;
use App\Filament\Pages\Concerns\UsesInventoryReportLayout;
use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryCurrentStockReport extends Page implements HasTable
{
    use InteractsWithTable;
    use InventoryReportPage;
    use UsesInventoryReportLayout;

    protected static ?string $slug = 'inventory-report-current-stock';

    protected static string $view = 'filament.pages.inventory-table-report';

    public function mount(): void
    {
        $this->mountInteractsWithTable();
        $this->mountInventoryReportLayout();
    }

    /**
     * @return list<array{label: string, value: string, tone: string}>
     */
    public function getReportStats(): array
    {
        $m = $this->inventorySummary;

        return [
            ['label' => 'Stock value', 'value' => number_format((float) ($m['stock_value'] ?? 0)).' BDT', 'tone' => 'orange'],
            ['label' => 'Active SKUs', 'value' => (string) ($m['product_count'] ?? 0), 'tone' => 'teal'],
            ['label' => 'Low stock', 'value' => (string) ($m['low_stock_count'] ?? 0), 'tone' => 'amber'],
            ['label' => 'Stock units', 'value' => number_format((int) ($m['stock_units'] ?? 0)), 'tone' => 'sky'],
        ];
    }

    public function getReportTitle(): string
    {
        return 'Current stock report';
    }

    public function getReportSubtitle(): string
    {
        return 'Active products · sellable quantity and stock value.';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Product::query()->where('is_active', true))
            ->columns([
                Tables\Columns\TextColumn::make('sku')->fontFamily('mono')->searchable(),
                Tables\Columns\TextColumn::make('name')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('stock_qty')->label('Qty')->sortable(),
                Tables\Columns\TextColumn::make('stock_value')
                    ->label('Value')
                    ->state(fn (Product $record): float => $record->stockValue())
                    ->money('BDT'),
                Tables\Columns\TextColumn::make('cost_price')->label('Cost/u')->money('BDT'),
                Tables\Columns\TextColumn::make('sell_price')->label('Sell/u')->money('BDT'),
                Tables\Columns\TextColumn::make('reorder_level')->label('Reorder'),
            ])
            ->defaultSort('name')
            ->paginated([25, 50, 100])
            ->headerActions([
                Tables\Actions\Action::make('products')
                    ->label('Manage products')
                    ->url(ProductResource::getUrl())
                    ->icon('heroicon-o-shopping-bag'),
            ]);
    }
}
