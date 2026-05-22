<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\ProductWarehouseStock;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class InventoryStockService
{
    public function __construct(
        private readonly WarehouseResolver $warehouses,
    ) {}

    /**
     * @return list<StockMovement>
     */
    public function receivePurchaseOrder(PurchaseOrder $order, ?User $user = null): array
    {
        if ($order->status === 'received') {
            throw new InvalidArgumentException('Purchase order already received.');
        }

        $movements = [];
        $warehouseId = $this->warehouses->resolveWarehouseId(
            (int) $order->tenant_id,
            $order->warehouse_id ? (int) $order->warehouse_id : null,
        );

        DB::transaction(function () use ($order, $user, $warehouseId, &$movements): void {
            $order->load('items.product');

            foreach ($order->items as $item) {
                if (! $item->product_id || ! $item->product) {
                    continue;
                }

                $qty = max(1, (int) $item->quantity);
                $unitCost = round((float) $item->unit_price, 2);

                $movements[] = $this->adjustStock(
                    $item->product,
                    $qty,
                    StockMovement::TYPE_PURCHASE,
                    $unitCost,
                    0,
                    PurchaseOrder::class,
                    (int) $order->id,
                    'PO '.$order->po_number,
                    $user,
                    $warehouseId,
                );

                $product = $item->product->fresh();
                $product->updateQuietly([
                    'last_purchase_cost' => $unitCost,
                    'cost_price' => $unitCost > 0 ? $unitCost : $product->cost_price,
                ]);
            }

            $order->update([
                'status' => 'received',
                'received_at' => now()->toDateString(),
                'warehouse_id' => $warehouseId,
            ]);
        });

        return $movements;
    }

    public function adjustStock(
        Product $product,
        int $quantityDelta,
        string $type,
        float $unitCost = 0,
        float $unitPrice = 0,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        ?User $user = null,
        ?int $warehouseId = null,
    ): StockMovement {
        if ($quantityDelta === 0) {
            throw new InvalidArgumentException('Quantity change cannot be zero.');
        }

        $warehouseId = $this->warehouses->resolveWarehouseId(
            (int) $product->tenant_id,
            $warehouseId,
        );

        return DB::transaction(function () use (
            $product,
            $quantityDelta,
            $type,
            $unitCost,
            $unitPrice,
            $referenceType,
            $referenceId,
            $notes,
            $user,
            $warehouseId,
        ): StockMovement {
            $product = Product::withoutGlobalScopes()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $level = ProductWarehouseStock::withoutGlobalScopes()
                ->where('product_id', $product->id)
                ->where('warehouse_id', $warehouseId)
                ->lockForUpdate()
                ->first();

            if (! $level) {
                $level = ProductWarehouseStock::withoutGlobalScopes()->create([
                    'tenant_id' => $product->tenant_id,
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouseId,
                    'stock_qty' => 0,
                ]);
            }

            $before = (int) $level->stock_qty;
            $after = $before + $quantityDelta;

            if ($after < 0) {
                throw new InvalidArgumentException(
                    "Insufficient stock for {$product->name} at this warehouse (have {$before}, need ".abs($quantityDelta).').',
                );
            }

            $level->update(['stock_qty' => $after]);
            $this->warehouses->syncProductTotal($product);

            $productTotalBefore = max(0, (int) $product->stock_qty - $quantityDelta);
            $productTotalAfter = (int) $product->fresh()->stock_qty;

            return StockMovement::create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'warehouse_id' => $warehouseId,
                'type' => $type,
                'quantity' => $quantityDelta,
                'unit_cost' => round($unitCost, 2),
                'unit_price' => round($unitPrice, 2),
                'stock_before' => $productTotalBefore,
                'stock_after' => $productTotalAfter,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'recorded_by' => $user?->id,
                'moved_at' => now(),
            ]);
        });
    }

    public function issueForSale(
        Product $product,
        int $quantity,
        float $unitCost,
        float $unitPrice,
        string $referenceType,
        int $referenceId,
        ?User $user = null,
        ?int $warehouseId = null,
    ): StockMovement {
        return $this->adjustStock(
            $product,
            -abs($quantity),
            StockMovement::TYPE_SALE,
            $unitCost,
            $unitPrice,
            $referenceType,
            $referenceId,
            'Retail sale',
            $user,
            $warehouseId,
        );
    }

    public function transfer(
        Product $product,
        int $fromWarehouseId,
        int $toWarehouseId,
        int $quantity,
        ?string $notes = null,
        ?User $user = null,
    ): void {
        if ($fromWarehouseId === $toWarehouseId) {
            throw new InvalidArgumentException('Source and destination warehouse must differ.');
        }

        $qty = max(1, $quantity);
        $cost = $product->effectiveCost();

        DB::transaction(function () use ($product, $fromWarehouseId, $toWarehouseId, $qty, $cost, $notes, $user): void {
            $this->adjustStock(
                $product,
                -$qty,
                StockMovement::TYPE_TRANSFER_OUT,
                $cost,
                0,
                null,
                null,
                $notes ?? 'Transfer out',
                $user,
                $fromWarehouseId,
            );

            $this->adjustStock(
                $product,
                $qty,
                StockMovement::TYPE_TRANSFER_IN,
                $cost,
                0,
                null,
                null,
                $notes ?? 'Transfer in',
                $user,
                $toWarehouseId,
            );
        });
    }
}
