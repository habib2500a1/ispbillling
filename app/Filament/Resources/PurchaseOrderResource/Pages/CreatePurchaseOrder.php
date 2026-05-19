<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

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
