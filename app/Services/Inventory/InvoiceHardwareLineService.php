<?php

namespace App\Services\Inventory;

use App\Models\Device;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Billing\InvoiceCalculator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class InvoiceHardwareLineService
{
    public function __construct(
        private readonly InventoryStockService $stock,
        private readonly WarehouseResolver $warehouses,
    ) {}

    public function addProductLine(
        Invoice $invoice,
        Product $product,
        int $quantity = 1,
        ?float $unitPrice = null,
        ?int $warehouseId = null,
        bool $issueStock = false,
        ?User $user = null,
    ): InvoiceItem {
        $qty = max(1, $quantity);
        $price = round($unitPrice ?? $product->effectiveSellPrice(), 2);
        $warehouseId = $this->warehouses->resolveWarehouseId((int) $invoice->tenant_id, $warehouseId);

        return DB::transaction(function () use ($invoice, $product, $qty, $price, $warehouseId, $issueStock, $user): InvoiceItem {
            $sort = (int) InvoiceItem::query()->where('invoice_id', $invoice->id)->max('sort_order') + 1;

            $item = InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'item_type' => 'hardware',
                'description' => $product->name.($product->sku ? ' ('.$product->sku.')' : ''),
                'quantity' => $qty,
                'unit_price' => $price,
                'line_total' => round($qty * $price, 2),
                'sort_order' => $sort,
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'stock_issued' => false,
                'meta' => [
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouseId,
                    'issue_stock' => $issueStock,
                ],
            ]);

            if ($issueStock) {
                $this->issueStockForItem($item->fresh(), $user);
            }

            InvoiceCalculator::recalculate($invoice->fresh());

            return $item->fresh();
        });
    }

    public function linkDeviceLine(
        Invoice $invoice,
        Device $device,
        ?float $unitPrice = null,
    ): InvoiceItem {
        if ($device->customer_id && (int) $device->customer_id !== (int) $invoice->customer_id) {
            throw new InvalidArgumentException('Device is assigned to another customer.');
        }

        $price = round($unitPrice ?? (float) ($device->lease_monthly_fee ?? 0), 2);
        $sort = (int) InvoiceItem::query()->where('invoice_id', $invoice->id)->max('sort_order') + 1;

        $item = InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'item_type' => 'onu_lease',
            'description' => 'ONU / device — '.($device->display_name ?: $device->serial_number),
            'quantity' => 1,
            'unit_price' => $price,
            'line_total' => $price,
            'sort_order' => $sort,
            'device_id' => $device->id,
            'product_id' => $device->product_id,
            'meta' => ['device_id' => $device->id],
        ]);

        InvoiceCalculator::recalculate($invoice->fresh());

        return $item;
    }

    public function issueStockForItem(InvoiceItem $item, ?User $user = null): InvoiceItem
    {
        if ($item->stock_issued || ! $item->product_id) {
            return $item;
        }

        $invoice = $item->invoice ?? $item->invoice()->firstOrFail();
        $product = Product::withoutGlobalScopes()->whereKey($item->product_id)->firstOrFail();
        $qty = max(1, (int) round((float) $item->quantity));
        $warehouseId = $this->warehouses->resolveWarehouseId(
            (int) $invoice->tenant_id,
            $item->warehouse_id ? (int) $item->warehouse_id : null,
        );

        $this->stock->adjustStock(
            $product,
            -$qty,
            StockMovement::TYPE_SALE,
            $product->effectiveCost(),
            (float) $item->unit_price,
            InvoiceItem::class,
            (int) $item->id,
            'Invoice '.$invoice->invoice_number.' hardware',
            $user,
            $warehouseId,
        );

        $item->update([
            'stock_issued' => true,
            'warehouse_id' => $warehouseId,
        ]);

        return $item->fresh();
    }
}
