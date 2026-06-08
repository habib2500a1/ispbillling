<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Pages\Concerns\UsesInventoryFormLayout;
use App\Filament\Resources\PurchaseOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseOrder extends CreateRecord
{
    use UsesInventoryFormLayout;

    protected static string $resource = PurchaseOrderResource::class;

    protected static string $view = 'filament.inventory.form-shell';

    public function mount(): void
    {
        parent::mount();
        $this->configureInventoryFormShell(
            'New purchase order',
            'Vendor lines · warehouse receive · stock in',
            PurchaseOrderResource::getUrl(),
            'Purchase orders',
        );
    }

    protected function getHeaderActions(): array
    {
        return [$this->inventoryHubAction()];
    }

    protected function afterCreate(): void
    {
        $this->recalculateTotal();
    }

    private function recalculateTotal(): void
    {
        $order = $this->record->fresh('items');
        $total = $order->items->sum(fn ($i) => (float) $i->line_total);
        $order->update(['total' => $total]);
    }
}
