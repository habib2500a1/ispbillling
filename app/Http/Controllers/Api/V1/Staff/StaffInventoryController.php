<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryDashboardService;
use App\Services\Inventory\InventorySaleService;
use App\Services\Inventory\ProductBarcodeLookup;
use App\Services\Inventory\WarehouseResolver;
use App\Support\Rbac\StaffCapability;
use App\Support\StaffTenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffInventoryController extends Controller
{
    public function bootstrap(Request $request, WarehouseResolver $warehouses, InventoryDashboardService $dashboard): JsonResponse
    {
        $user = $this->staffWithInventory($request);
        $tenantId = StaffTenantScope::tenantIdFor($user);

        $warehouseRows = Warehouse::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'is_default']);

        $defaultId = $warehouses->defaultWarehouseId($tenantId);

        return response()->json([
            'summary' => $dashboard->summary($tenantId),
            'default_warehouse_id' => $defaultId,
            'warehouses' => $warehouseRows->map(fn (Warehouse $w): array => [
                'id' => $w->id,
                'code' => $w->code,
                'name' => $w->name,
                'label' => $w->displayLabel(),
                'is_default' => $w->is_default,
            ])->values(),
            'payment_methods' => [
                ['code' => 'cash', 'label' => 'Cash'],
                ['code' => 'bkash', 'label' => 'bKash'],
                ['code' => 'nagad', 'label' => 'Nagad'],
                ['code' => 'bank', 'label' => 'Bank'],
            ],
            'staff_cash_to_wallet' => (bool) config('inventory.staff_sale_to_collector_wallet', true),
        ]);
    }

    public function products(Request $request, ProductBarcodeLookup $lookup, WarehouseResolver $warehouses): JsonResponse
    {
        $user = $this->staffWithInventory($request);
        $tenantId = StaffTenantScope::tenantIdFor($user);

        $data = $request->validate([
            'barcode' => ['nullable', 'string', 'max:64'],
            'q' => ['nullable', 'string', 'max:100'],
            'warehouse_id' => ['nullable', 'integer'],
        ]);

        $warehouseId = $warehouses->resolveWarehouseId(
            $tenantId,
            isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null,
        );

        if (! empty($data['barcode'])) {
            $product = $lookup->find($tenantId, $data['barcode']);

            return response()->json([
                'data' => $product ? [$this->productPayload($product, $warehouseId)] : [],
            ]);
        }

        $q = trim((string) ($data['q'] ?? ''));
        if (strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $products = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($query) use ($q): void {
                $query->where('name', 'like', '%'.$q.'%')
                    ->orWhere('sku', 'like', '%'.$q.'%')
                    ->orWhere('barcode', 'like', '%'.$q.'%');
            })
            ->orderBy('name')
            ->limit(40)
            ->get();

        return response()->json([
            'data' => $products->map(fn (Product $p) => $this->productPayload($p, $warehouseId))->values(),
        ]);
    }

    public function store(Request $request, InventorySaleService $sales): JsonResponse
    {
        $user = $this->staffWithInventory($request);
        $tenantId = StaffTenantScope::tenantIdFor($user);

        $data = $request->validate([
            'warehouse_id' => ['nullable', 'integer'],
            'payment_method' => ['required', 'string', Rule::in(['cash', 'bkash', 'nagad', 'bank', 'counter'])],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:500'],
            'barcode_scan' => ['nullable', 'string', 'max:64'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $sale = $sales->recordSale(
            tenantId: $tenantId,
            lines: $data['lines'],
            channel: 'field',
            customerName: $data['customer_name'] ?? null,
            customerPhone: $data['customer_phone'] ?? null,
            discount: (float) ($data['discount'] ?? 0),
            paymentMethod: (string) $data['payment_method'],
            notes: $data['notes'] ?? null,
            user: $user,
            warehouseId: isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null,
            barcodeScan: $data['barcode_scan'] ?? null,
        );

        $walletNote = null;
        if ($user->hasAnyRole(['cashier', 'collector', 'branch-manager', 'super-admin', 'isp-admin'])
            && in_array($sale->payment_method, config('inventory.staff_collector_cash_methods', ['cash', 'counter']), true)) {
            $walletNote = 'Full sale amount added to your cash-in-hand wallet.';
        }

        return response()->json([
            'message' => 'Sale recorded · '.$sale->sale_number,
            'sale' => [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'total' => (float) $sale->total,
                'gross_profit' => (float) $sale->gross_profit,
                'payment_method' => $sale->payment_method,
                'sold_at' => $sale->sold_at?->toIso8601String(),
            ],
            'wallet_note' => $walletNote,
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(Product $product, int $warehouseId): array
    {
        $stockAt = app(WarehouseResolver::class)->stockAt($product, $warehouseId);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'sell_price' => $product->effectiveSellPrice(),
            'stock_at_warehouse' => $stockAt,
            'stock_total' => (int) $product->stock_qty,
        ];
    }

    private function staffWithInventory(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless(StaffCapability::for($user)->canInventory(), 403, 'Inventory access required.');

        return $user;
    }
}
