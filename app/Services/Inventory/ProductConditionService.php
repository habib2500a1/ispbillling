<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ProductConditionService
{
    public function __construct(
        private readonly InventoryStockService $stock,
    ) {}

    /**
     * @param  'damaged'|'missing'  $kind
     */
    public function record(
        Product $product,
        string $kind,
        int $quantity,
        bool $reduceStock,
        ?User $user = null,
        ?string $notes = null,
    ): void {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        if (! in_array($kind, ['damaged', 'missing'], true)) {
            throw new InvalidArgumentException('Invalid condition kind.');
        }

        DB::transaction(function () use ($product, $kind, $quantity, $reduceStock, $user, $notes): void {
            if ($reduceStock) {
                if ((int) $product->stock_qty < $quantity) {
                    throw new InvalidArgumentException('Not enough stock on hand.');
                }

                $this->stock->adjustStock(
                    $product,
                    -$quantity,
                    StockMovement::TYPE_ADJUSTMENT_OUT,
                    $product->effectiveCost(),
                    0,
                    null,
                    null,
                    ucfirst($kind).($notes ? ': '.$notes : ''),
                    $user,
                );
                $product->refresh();
            }

            $column = $kind === 'damaged' ? 'damaged_qty' : 'missing_qty';
            $product->update([
                $column => (int) $product->{$column} + $quantity,
            ]);
        });

        InventoryDashboardService::flushSummaryCache((int) $product->tenant_id);
    }
}
