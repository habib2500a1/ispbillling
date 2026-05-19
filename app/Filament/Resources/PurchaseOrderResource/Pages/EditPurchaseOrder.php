<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function afterSave(): void
    {
        $order = $this->record->fresh('items');
        foreach ($order->items as $item) {
            $item->update(['line_total' => $item->quantity * $item->unit_price]);
        }
        $order->update(['total' => $order->items->sum('line_total')]);
    }
}
