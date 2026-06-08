<?php

namespace App\Filament\Resources\VendorResource\Pages;

use App\Filament\Pages\Concerns\UsesInventoryListLayout;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\VendorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVendors extends ListRecords
{
    use UsesInventoryListLayout;

    protected static string $resource = VendorResource::class;

    protected static string $view = 'filament.inventory.list-shell';

    public function mount(): void
    {
        parent::mount();
        $this->mountInventoryListLayout();

        $m = $this->inventorySummary;
        $this->inventoryListTitle = 'Vendors';
        $this->inventoryListSubtitle = 'Supplier profiles · purchase history · warranty performance';
        $this->inventoryListCreateUrl = VendorResource::getUrl('create');
        $this->inventoryListCreateLabel = 'New vendor';
        $this->inventoryListStats = [
            ['label' => 'Open POs', 'value' => (string) ($m['pending_purchases'] ?? 0), 'tone' => 'violet'],
            ['label' => 'PO received (mo)', 'value' => number_format((float) ($m['purchase_month_bdt'] ?? 0)).' BDT', 'tone' => 'orange'],
            ['label' => 'Warranty alerts', 'value' => (string) ($m['warranty_expiring'] ?? 0), 'tone' => 'amber'],
            ['label' => 'Stock value', 'value' => number_format((float) ($m['stock_value'] ?? 0)).' BDT', 'tone' => 'teal'],
        ];
        $this->inventoryListLinks = [
            ['label' => 'Purchase orders', 'url' => PurchaseOrderResource::getUrl()],
            ['label' => 'Warranty center', 'url' => \App\Filament\Pages\InventoryWarrantyManagement::getUrl()],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->inventoryHubAction(),
            Actions\CreateAction::make()->label('New vendor'),
        ];
    }
}
