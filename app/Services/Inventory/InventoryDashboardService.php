<?php

namespace App\Services\Inventory;

use App\Models\InventorySale;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use App\Support\TenantResolver;

final class InventoryDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        $products = Product::withoutGlobalScopes()->where('tenant_id', $tenantId);
        $active = (clone $products)->where('is_active', true);

        $stockValue = 0.0;
        $lowStock = 0;
        (clone $active)->get()->each(function (Product $p) use (&$stockValue, &$lowStock): void {
            $stockValue += $p->stockValue();
            if ($p->isLowStock()) {
                $lowStock++;
            }
        });

        $from = now()->startOfMonth();
        $sales = InventorySale::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('sold_at', '>=', $from);

        return [
            'product_count' => (clone $active)->count(),
            'stock_units' => (int) (clone $active)->sum('stock_qty'),
            'stock_value' => round($stockValue, 2),
            'low_stock_count' => $lowStock,
            'open_po_count' => PurchaseOrder::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['draft', 'ordered'])
                ->count(),
            'month_sales' => round((float) (clone $sales)->sum('total'), 2),
            'month_profit' => round((float) (clone $sales)->sum('gross_profit'), 2),
            'month_cogs' => round((float) (clone $sales)->sum('total_cost'), 2),
            'shop_products' => (clone $active)->where('show_on_shop', true)->where('stock_qty', '>', 0)->count(),
            'warehouse_count' => Warehouse::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->count(),
        ];
    }
}
