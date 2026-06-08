<?php

namespace App\Filament\Resources\StockMovementResource\Pages;

use App\Filament\Pages\Concerns\UsesInventoryListLayout;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\StockMovementResource;
use App\Filament\Resources\WarehouseResource;
use Filament\Resources\Pages\ListRecords;

class ListStockMovements extends ListRecords
{
    use UsesInventoryListLayout;

    protected static string $resource = StockMovementResource::class;

    protected static string $view = 'filament.inventory.list-shell';

    public function mount(): void
    {
        parent::mount();
        $this->mountInventoryListLayout();

        $m = $this->inventorySummary;
        $this->inventoryListTitle = 'Stock ledger';
        $this->inventoryListSubtitle = 'Per-warehouse in/out audit · transfers · adjustments';
        $this->inventoryListCreateUrl = null;
        $this->inventoryListStats = [
            ['label' => 'Movements (month)', 'value' => number_format((int) ($m['stock_movements_month'] ?? 0)), 'tone' => 'cyan'],
            ['label' => 'Stock units', 'value' => number_format((int) ($m['stock_units'] ?? 0)), 'tone' => 'teal'],
            ['label' => 'Low stock', 'value' => (string) ($m['low_stock_count'] ?? 0), 'tone' => 'amber'],
            ['label' => 'Damaged SKUs', 'value' => (string) ($m['damaged_missing_count'] ?? 0), 'tone' => 'rose'],
        ];
        $this->inventoryListLinks = [
            ['label' => 'Products', 'url' => ProductResource::getUrl()],
            ['label' => 'Warehouses', 'url' => WarehouseResource::getUrl()],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->inventoryHubAction(),
        ];
    }
}
