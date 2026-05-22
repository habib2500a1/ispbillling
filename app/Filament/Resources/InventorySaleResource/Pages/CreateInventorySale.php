<?php

namespace App\Filament\Resources\InventorySaleResource\Pages;

use App\Filament\Resources\InventorySaleResource;
use App\Services\Inventory\InventorySaleService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateInventorySale extends CreateRecord
{
    protected static string $resource = InventorySaleResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $lines = [];
        foreach ($data['lines'] ?? [] as $line) {
            if (empty($line['product_id'])) {
                continue;
            }
            $lines[] = [
                'product_id' => (int) $line['product_id'],
                'quantity' => (int) ($line['quantity'] ?? 1),
                'unit_price' => (float) ($line['unit_price'] ?? 0),
            ];
        }

        $sale = app(InventorySaleService::class)->recordSale(
            tenantId: (int) auth()->user()->tenant_id,
            lines: $lines,
            channel: (string) ($data['channel'] ?? 'counter'),
            customerName: $data['customer_name'] ?? null,
            customerPhone: $data['customer_phone'] ?? null,
            discount: (float) ($data['discount'] ?? 0),
            paymentMethod: (string) ($data['payment_method'] ?? 'cash'),
            notes: $data['notes'] ?? null,
            user: auth()->user(),
            warehouseId: isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null,
            barcodeScan: $data['barcode_scan'] ?? null,
        );

        $walletNote = in_array($sale->payment_method, config('inventory.staff_collector_cash_methods', ['cash', 'counter']), true)
            ? ' · Full '.number_format((float) $sale->total, 2).' BDT in your cash-in-hand (settle later)'
            : '';

        Notification::make()
            ->title('Sale recorded')
            ->body($sale->sale_number.' · Profit '.number_format((float) $sale->gross_profit, 2).' BDT · GL posted'.$walletNote)
            ->success()
            ->send();

        return $sale;
    }

    protected function getRedirectUrl(): string
    {
        return InventorySaleResource::getUrl('view', ['record' => $this->record]);
    }
}
