<?php

namespace App\Services\Inventory;

use App\Models\InventorySale;
use App\Models\InventorySaleItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class InventorySaleService
{
    public function __construct(
        private readonly InventoryStockService $stock,
        private readonly InventoryAccountingService $accounting,
    ) {}

    /**
     * @param  list<array{product_id: int, quantity: int, unit_price?: float}>  $lines
     */
    public function recordSale(
        int $tenantId,
        array $lines,
        string $channel = 'counter',
        ?string $customerName = null,
        ?string $customerPhone = null,
        float $discount = 0,
        string $paymentMethod = 'cash',
        ?string $notes = null,
        ?User $user = null,
        ?int $warehouseId = null,
        ?string $barcodeScan = null,
    ): InventorySale {
        if ($barcodeScan !== null && trim($barcodeScan) !== '') {
            $scanned = app(ProductBarcodeLookup::class)->find($tenantId, $barcodeScan);
            if (! $scanned) {
                throw new InvalidArgumentException('No product found for barcode/SKU: '.$barcodeScan);
            }
            $lines[] = ['product_id' => $scanned->id, 'quantity' => 1];
        }

        if ($lines === []) {
            throw new InvalidArgumentException('Add at least one product line.');
        }

        $warehouseId = app(WarehouseResolver::class)->resolveWarehouseId($tenantId, $warehouseId);

        return DB::transaction(function () use (
            $tenantId,
            $lines,
            $channel,
            $customerName,
            $customerPhone,
            $discount,
            $paymentMethod,
            $notes,
            $user,
            $warehouseId,
        ): InventorySale {
            $subtotal = 0.0;
            $totalCost = 0.0;
            $saleItems = [];

            foreach ($lines as $line) {
                $product = Product::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->whereKey((int) $line['product_id'])
                    ->firstOrFail();

                $qty = max(1, (int) ($line['quantity'] ?? 1));
                $unitPrice = round((float) ($line['unit_price'] ?? $product->effectiveSellPrice()), 2);
                $unitCost = $product->effectiveCost();
                $lineTotal = round($unitPrice * $qty, 2);
                $lineCost = round($unitCost * $qty, 2);

                $subtotal += $lineTotal;
                $totalCost += $lineCost;

                $saleItems[] = [
                    'product' => $product,
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'line_cost' => $lineCost,
                    'line_profit' => round($lineTotal - $lineCost, 2),
                ];
            }

            $discount = round(max(0, $discount), 2);
            $total = round(max(0, $subtotal - $discount), 2);
            $grossProfit = round(max(0, $total - $totalCost), 2);

            $sale = InventorySale::create([
                'tenant_id' => $tenantId,
                'warehouse_id' => $warehouseId,
                'sale_number' => InventorySale::generateSaleNumber($tenantId),
                'channel' => $channel,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'total_cost' => $totalCost,
                'gross_profit' => $grossProfit,
                'payment_method' => $paymentMethod,
                'status' => 'completed',
                'notes' => $notes,
                'recorded_by' => $user?->id,
                'sold_at' => now(),
            ]);

            foreach ($saleItems as $row) {
                InventorySaleItem::create([
                    'inventory_sale_id' => $sale->id,
                    'product_id' => $row['product']->id,
                    'description' => $row['product']->name,
                    'quantity' => $row['qty'],
                    'unit_cost' => $row['unit_cost'],
                    'unit_price' => $row['unit_price'],
                    'line_total' => $row['line_total'],
                    'line_cost' => $row['line_cost'],
                    'line_profit' => $row['line_profit'],
                ]);

                $this->stock->issueForSale(
                    $row['product'],
                    $row['qty'],
                    $row['unit_cost'],
                    $row['unit_price'],
                    InventorySale::class,
                    (int) $sale->id,
                    $user,
                    $warehouseId,
                );
            }

            $sale = $sale->fresh(['items']);

            $this->accounting->postRetailSale($sale);
            app(InventoryStaffCollectionService::class)->recordFromSale($sale);

            return $sale;
        });
    }
}
