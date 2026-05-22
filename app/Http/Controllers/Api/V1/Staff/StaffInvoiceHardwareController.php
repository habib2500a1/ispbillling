<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InvoiceHardwareLineService;
use App\Services\Inventory\ProductBarcodeLookup;
use App\Services\Inventory\WarehouseResolver;
use App\Support\Rbac\StaffCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffInvoiceHardwareController extends Controller
{
    public function options(Request $request, int $invoice, WarehouseResolver $warehouses, ProductBarcodeLookup $lookup): JsonResponse
    {
        $user = $this->authorize($request);
        $record = $this->resolveInvoice($user, $invoice);

        $tenantId = (int) $record->tenant_id;
        $warehouseId = $warehouses->defaultWarehouseId($tenantId);

        $products = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(80)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'sell_price' => $p->effectiveSellPrice(),
                'stock_at_warehouse' => $warehouses->stockAt($p, $warehouseId),
            ]);

        return response()->json([
            'invoice' => [
                'id' => $record->id,
                'invoice_number' => $record->invoice_number,
                'status' => $record->status,
                'customer_id' => $record->customer_id,
                'total' => (float) $record->total,
                'balance_due' => round(max(0, (float) $record->total - (float) $record->amount_paid), 2),
            ],
            'default_warehouse_id' => $warehouseId,
            'warehouses' => \App\Models\Warehouse::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'is_default'])
                ->map(fn ($w) => [
                    'id' => $w->id,
                    'label' => $w->displayLabel(),
                    'is_default' => $w->is_default,
                ]),
            'products' => $products,
        ]);
    }

    public function lookupProduct(Request $request, int $invoice, ProductBarcodeLookup $lookup, WarehouseResolver $warehouses): JsonResponse
    {
        $user = $this->authorize($request);
        $record = $this->resolveInvoice($user, $invoice);

        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:64'],
            'warehouse_id' => ['nullable', 'integer'],
        ]);

        $tenantId = (int) $record->tenant_id;
        $product = $lookup->find($tenantId, $data['barcode']);
        if (! $product) {
            return response()->json(['message' => 'Product not found.', 'data' => null], 404);
        }

        $warehouseId = $warehouses->resolveWarehouseId($tenantId, isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null);

        return response()->json([
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'sell_price' => $product->effectiveSellPrice(),
                'stock_at_warehouse' => $warehouses->stockAt($product, $warehouseId),
            ],
        ]);
    }

    public function store(Request $request, int $invoice, InvoiceHardwareLineService $hardware): JsonResponse
    {
        $user = $this->authorize($request);
        $record = $this->resolveInvoice($user, $invoice);

        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'warehouse_id' => ['nullable', 'integer'],
            'issue_stock' => ['nullable', 'boolean'],
        ]);

        $product = Product::withoutGlobalScopes()
            ->where('tenant_id', $record->tenant_id)
            ->whereKey($data['product_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $item = $hardware->addProductLine(
            $record,
            $product,
            (int) ($data['quantity'] ?? 1),
            isset($data['unit_price']) ? (float) $data['unit_price'] : null,
            isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null,
            (bool) ($data['issue_stock'] ?? false),
            $user,
        );

        $record = $record->fresh();

        return response()->json([
            'message' => 'Hardware line added to invoice.',
            'item' => [
                'id' => $item->id,
                'description' => $item->description,
                'line_total' => (float) $item->line_total,
                'stock_issued' => $item->stock_issued,
            ],
            'invoice' => [
                'id' => $record->id,
                'total' => (float) $record->total,
                'balance_due' => round(max(0, (float) $record->total - (float) $record->amount_paid), 2),
            ],
        ], 201);
    }

    private function authorize(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless(StaffCapability::for($user)->canInventory(), 403, 'Inventory access required.');

        return $user;
    }

    private function resolveInvoice(User $user, int $invoiceId): Invoice
    {
        $invoice = Invoice::withoutGlobalScopes()->whereKey($invoiceId)->firstOrFail();

        if ($user->tenant_id !== null && (int) $invoice->tenant_id !== (int) $user->tenant_id) {
            abort(404);
        }

        abort_unless(
            in_array($invoice->status, ['open', 'partial', 'draft'], true),
            422,
            'Invoice cannot be edited in this status.',
        );

        return $invoice;
    }
}
