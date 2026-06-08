<?php

namespace App\Services\Inventory;

use App\Models\Device;
use App\Models\FixedAsset;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\StoreDeviceLoan;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only asset intelligence metrics for inventory UI dashboards.
 * Does not mutate stock, assignments, or procurement logic.
 */
final class InventoryAssetIntelligenceService
{
    private const CACHE_SECONDS = 120;

    /**
     * @return array<string, mixed>
     */
    public function metrics(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        return Cache::remember(
            'inventory_asset_intel:'.$tenantId,
            self::CACHE_SECONDS,
            fn (): array => $this->computeMetrics($tenantId),
        );
    }

    public static function flushCache(int $tenantId): void
    {
        Cache::forget('inventory_asset_intel:'.$tenantId);
        InventoryDashboardService::flushSummaryCache($tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    private function computeMetrics(int $tenantId): array
    {
        $summary = app(InventoryDashboardService::class)->summary($tenantId);

        $devices = Device::withoutGlobalScopes()->where('tenant_id', $tenantId);
        $fixedAssets = FixedAsset::withoutGlobalScopes()->where('tenant_id', $tenantId);

        $totalDevices = (int) (clone $devices)->count();
        $totalFixed = (int) (clone $fixedAssets)->count();
        $activeDevices = (int) (clone $devices)->whereIn('status', ['in_stock', 'assigned'])->count();
        $assignedDevices = (int) (clone $devices)->where(function ($q): void {
            $q->where('status', 'assigned')->orWhereNotNull('customer_id');
        })->count();
        $unassignedDevices = max(0, $totalDevices - $assignedDevices);
        $damagedDevices = (int) (clone $devices)->where('status', 'faulty')->count();
        $damagedProducts = (int) ($summary['damaged_missing_count'] ?? 0);
        $warrantyExpiring = (int) (clone $devices)
            ->whereNotNull('warranty_expires_at')
            ->whereBetween('warranty_expires_at', [now(), now()->addDays(30)])
            ->count();

        $loansIssued = (int) ($summary['support_out_count'] ?? 0);
        $loansReturned = (int) StoreDeviceLoan::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', StoreDeviceLoan::STATUS_RETURNED)
            ->count();

        $monthFrom = now()->startOfMonth();
        $stockMovementsMonth = (int) StockMovement::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('moved_at', '>=', $monthFrom)
            ->count();

        $purchaseMonth = (float) PurchaseOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'received')
            ->where('received_at', '>=', $monthFrom)
            ->sum('total');

        return [
            ...$summary,
            'total_assets' => $totalDevices + $totalFixed + (int) ($summary['product_count'] ?? 0),
            'total_devices' => $totalDevices,
            'total_fixed_assets' => $totalFixed,
            'active_assets' => $activeDevices + (int) (clone $fixedAssets)->where('status', 'active')->count(),
            'assigned_assets' => $assignedDevices + $loansIssued,
            'unassigned_assets' => $unassignedDevices,
            'damaged_assets' => $damagedDevices + $damagedProducts,
            'pending_purchases' => (int) ($summary['open_po_count'] ?? 0),
            'warranty_expiring' => $warrantyExpiring,
            'loans_returned' => $loansReturned,
            'stock_movements_month' => $stockMovementsMonth,
            'purchase_month_bdt' => round($purchaseMonth, 2),
            'asset_types' => $this->assetTypeBreakdown($tenantId),
            'lifecycle' => $this->lifecycleCounts($tenantId, $devices, $fixedAssets, $summary),
            'alerts' => $this->smartAlerts($summary, $warrantyExpiring, $damagedDevices, $damagedProducts),
            'analytics' => $this->analyticsSnapshot($tenantId, $summary, $devices, $stockMovementsMonth, $purchaseMonth),
        ];
    }

    /**
     * @return list<array{key: string, label: string, count: int, icon: string}>
     */
    private function assetTypeBreakdown(int $tenantId): array
    {
        $devices = Device::withoutGlobalScopes()->where('tenant_id', $tenantId);
        $products = Product::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('is_active', true);

        $deviceType = static function (string $type) use ($devices): int {
            return (int) (clone $devices)->where('type', $type)->count();
        };

        $productMatch = static function (array $terms) use ($products): int {
            return (int) (clone $products)->where(function ($q) use ($terms): void {
                foreach ($terms as $term) {
                    $like = '%'.$term.'%';
                    $q->orWhere('name', 'like', $like)
                        ->orWhere('sku', 'like', $like)
                        ->orWhere('barcode', 'like', $like);
                }
            })->count();
        };

        return [
            ['key' => 'olt', 'label' => 'OLT', 'count' => $deviceType('olt'), 'icon' => 'heroicon-o-server-stack'],
            ['key' => 'onu', 'label' => 'ONU', 'count' => $deviceType('onu'), 'icon' => 'heroicon-o-signal'],
            ['key' => 'router', 'label' => 'Router', 'count' => $deviceType('router'), 'icon' => 'heroicon-o-wifi'],
            ['key' => 'switch', 'label' => 'Switch', 'count' => $deviceType('switch'), 'icon' => 'heroicon-o-arrows-right-left'],
            ['key' => 'fiber', 'label' => 'Fiber cable', 'count' => $productMatch(['fiber', 'cable', 'ftth']), 'icon' => 'heroicon-o-bolt'],
            ['key' => 'splitter', 'label' => 'Splitter', 'count' => $productMatch(['splitter', 'plc']), 'icon' => 'heroicon-o-share'],
            ['key' => 'junction', 'label' => 'Junction box', 'count' => $productMatch(['junction', 'jb ', 'box']), 'icon' => 'heroicon-o-cube'],
            ['key' => 'sfp', 'label' => 'SFP', 'count' => $productMatch(['sfp', 'transceiver']), 'icon' => 'heroicon-o-light-bulb'],
            ['key' => 'ups', 'label' => 'UPS', 'count' => $productMatch(['ups', 'power']), 'icon' => 'heroicon-o-battery-100'],
            ['key' => 'battery', 'label' => 'Battery', 'count' => $productMatch(['battery', 'lipo']), 'icon' => 'heroicon-o-battery-50'],
            ['key' => 'tools', 'label' => 'Technician tools', 'count' => $productMatch(['tool', 'splicer', 'otdr', 'cleaver']), 'icon' => 'heroicon-o-wrench-screwdriver'],
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Device>  $devices
     * @param  \Illuminate\Database\Eloquent\Builder<FixedAsset>  $fixedAssets
     * @param  array<string, mixed>  $summary
     * @return list<array{key: string, label: string, count: int, desc: string}>
     */
    private function lifecycleCounts(int $tenantId, $devices, $fixedAssets, array $summary): array
    {
        return [
            [
                'key' => 'purchased',
                'label' => 'Purchased',
                'count' => (int) ($summary['open_po_count'] ?? 0),
                'desc' => 'Open purchase orders',
            ],
            [
                'key' => 'stored',
                'label' => 'Stored',
                'count' => (int) (clone $devices)->where('status', 'in_stock')->count(),
                'desc' => 'Warehouse & in-stock CPE',
            ],
            [
                'key' => 'assigned',
                'label' => 'Assigned',
                'count' => (int) (clone $devices)->where('status', 'assigned')->count() + (int) ($summary['support_out_count'] ?? 0),
                'desc' => 'Staff loans & assigned CPE',
            ],
            [
                'key' => 'installed',
                'label' => 'Installed',
                'count' => (int) (clone $devices)->whereNotNull('customer_id')->count(),
                'desc' => 'Linked to subscribers',
            ],
            [
                'key' => 'maintained',
                'label' => 'Maintained',
                'count' => (int) (clone $devices)->where('warranty_status', Device::WARRANTY_ACTIVE)->count(),
                'desc' => 'Active warranty coverage',
            ],
            [
                'key' => 'returned',
                'label' => 'Returned',
                'count' => (int) StoreDeviceLoan::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('status', StoreDeviceLoan::STATUS_RETURNED)
                    ->count(),
                'desc' => 'Support device returns',
            ],
            [
                'key' => 'retired',
                'label' => 'Retired',
                'count' => (int) (clone $devices)->where('status', 'faulty')->count()
                    + (int) (clone $fixedAssets)->whereIn('status', ['disposed', 'damaged'])->count(),
                'desc' => 'Faulty / disposed assets',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return list<array{label: string, count: int, tone: string, url: string}>
     */
    private function smartAlerts(array $summary, int $warrantyExpiring, int $damagedDevices, int $damagedProducts): array
    {
        return [
            [
                'label' => 'Low stock SKUs',
                'count' => (int) ($summary['low_stock_count'] ?? 0),
                'tone' => 'amber',
                'url' => \App\Filament\Resources\ProductResource::getUrl(),
            ],
            [
                'label' => 'Asset failures',
                'count' => $damagedDevices + $damagedProducts,
                'tone' => 'rose',
                'url' => \App\Filament\Pages\InventoryDamagedMissingReport::getUrl(),
            ],
            [
                'label' => 'Warranty expiring (30d)',
                'count' => $warrantyExpiring,
                'tone' => 'orange',
                'url' => \App\Filament\Pages\InventoryWarrantyManagement::getUrl(),
            ],
            [
                'label' => 'Overdue loan returns',
                'count' => (int) ($summary['loans_overdue'] ?? 0),
                'tone' => 'red',
                'url' => \App\Filament\Pages\InventoryLoansOverdueReport::getUrl(),
            ],
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Device>  $devices
     * @param  array<string, mixed>  $summary
     * @return list<array{label: string, value: string, hint: string}>
     */
    private function analyticsSnapshot(int $tenantId, array $summary, $devices, int $stockMovementsMonth, float $purchaseMonth): array
    {
        $totalDevices = max(1, (int) (clone $devices)->count());
        $utilization = round(((int) (clone $devices)->whereNotNull('customer_id')->count() / $totalDevices) * 100, 1);

        $salesTrend = (float) ($summary['month_sales'] ?? 0);

        return [
            [
                'label' => 'Asset utilization',
                'value' => $utilization.'%',
                'hint' => 'CPE linked to subscribers',
            ],
            [
                'label' => 'Stock movements',
                'value' => number_format($stockMovementsMonth),
                'hint' => 'Ledger entries this month',
            ],
            [
                'label' => 'Purchase trend',
                'value' => number_format($purchaseMonth, 0).' BDT',
                'hint' => 'PO received this month',
            ],
            [
                'label' => 'Retail revenue',
                'value' => number_format($salesTrend, 0).' BDT',
                'hint' => 'POS sales this month',
            ],
        ];
    }
}
