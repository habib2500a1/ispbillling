<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Pages\Concerns\UsesInventoryListLayout;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\VendorResource;
use App\Filament\Resources\WarehouseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseOrders extends ListRecords
{
    use UsesInventoryListLayout;

    protected static string $resource = PurchaseOrderResource::class;

    protected static string $view = 'filament.inventory.list-shell';

    public function mount(): void
    {
        parent::mount();
        $this->mountInventoryListLayout();

        $m = $this->inventorySummary;
        $this->inventoryListTitle = 'Purchase orders';
        $this->inventoryListSubtitle = 'Procurement · receive into warehouse · vendor payable';
        $this->inventoryListCreateUrl = PurchaseOrderResource::getUrl('create');
        $this->inventoryListCreateLabel = 'New PO';
        $this->inventoryListStats = [
            ['label' => 'Pending POs', 'value' => (string) ($m['pending_purchases'] ?? 0), 'tone' => 'orange'],
            ['label' => 'Received (month)', 'value' => number_format((float) ($m['purchase_month_bdt'] ?? 0)).' BDT', 'tone' => 'emerald'],
            ['label' => 'Warehouses', 'value' => (string) ($m['warehouse_count'] ?? 0), 'tone' => 'amber'],
            ['label' => 'Stock value', 'value' => number_format((float) ($m['stock_value'] ?? 0)).' BDT', 'tone' => 'teal'],
        ];
        $this->inventoryListLinks = [
            ['label' => 'Vendors', 'url' => VendorResource::getUrl()],
            ['label' => 'Warehouses', 'url' => WarehouseResource::getUrl()],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->inventoryHubAction(),
            Actions\CreateAction::make()->label('New PO'),
        ];
    }
}
