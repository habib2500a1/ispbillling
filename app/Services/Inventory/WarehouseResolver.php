<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\ProductWarehouseStock;
use App\Models\Warehouse;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\DB;

final class WarehouseResolver
{
    public function defaultWarehouseId(int $tenantId): int
    {
        return $this->defaultWarehouse($tenantId)->id;
    }

    public function defaultWarehouse(int $tenantId): Warehouse
    {
        if ($tenantId < 1) {
            $tenantId = TenantResolver::requiredTenantId();
        }

        $existing = Warehouse::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($tenantId): Warehouse {
            $any = Warehouse::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            if ($any) {
                $any->update(['is_default' => true]);

                return $any->fresh();
            }

            return Warehouse::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'code' => config('inventory.default_warehouse_code', 'MAIN'),
                'name' => 'Main warehouse',
                'is_default' => true,
                'is_active' => true,
            ]);
        });
    }

    public function resolveWarehouseId(int $tenantId, ?int $warehouseId): int
    {
        if ($warehouseId) {
            $found = Warehouse::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereKey($warehouseId)
                ->where('is_active', true)
                ->exists();

            if ($found) {
                return $warehouseId;
            }
        }

        return $this->defaultWarehouseId($tenantId);
    }

    public function stockAt(Product $product, int $warehouseId): int
    {
        $row = ProductWarehouseStock::withoutGlobalScopes()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouseId)
            ->first();

        return (int) ($row?->stock_qty ?? 0);
    }

    public function syncProductTotal(Product $product): void
    {
        $total = (int) ProductWarehouseStock::withoutGlobalScopes()
            ->where('product_id', $product->id)
            ->sum('stock_qty');

        Product::withoutGlobalScopes()->whereKey($product->id)->update(['stock_qty' => $total]);
    }
}
