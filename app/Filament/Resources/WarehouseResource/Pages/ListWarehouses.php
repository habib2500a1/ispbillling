<?php

namespace App\Filament\Resources\WarehouseResource\Pages;

use App\Filament\Pages\Concerns\UsesInventoryListLayout;
use App\Filament\Resources\WarehouseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWarehouses extends ListRecords
{
    use UsesInventoryListLayout;

    protected static string $resource = WarehouseResource::class;

    protected static string $view = 'filament.inventory.list-shell';

    public function mount(): void
    {
        parent::mount();
        $this->mountInventoryListLayout();

        $m = $this->inventorySummary;
        $this->inventoryListTitle = 'Warehouses';
        $this->inventoryListSubtitle = 'Multi-location stock · transfers · monitoring';
        $this->inventoryListCreateUrl = WarehouseResource::getUrl('create');
        $this->inventoryListCreateLabel = 'New warehouse';
        $this->inventoryListStats = [
            ['label' => 'Active sites', 'value' => (string) ($m['warehouse_count'] ?? 0), 'tone' => 'amber'],
            ['label' => 'Stock units', 'value' => number_format((int) ($m['stock_units'] ?? 0)), 'tone' => 'teal'],
            ['label' => 'Stock value', 'value' => number_format((float) ($m['stock_value'] ?? 0)).' BDT', 'tone' => 'orange'],
            ['label' => 'Low stock SKUs', 'value' => (string) ($m['low_stock_count'] ?? 0), 'tone' => 'sky'],
        ];
        $this->inventoryListLinks = [
            ['label' => 'Stock ledger', 'url' => \App\Filament\Resources\StockMovementResource::getUrl()],
            ['label' => 'Products', 'url' => \App\Filament\Resources\ProductResource::getUrl()],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->inventoryHubAction(),
            Actions\CreateAction::make()->label('New warehouse'),
        ];
    }
}
