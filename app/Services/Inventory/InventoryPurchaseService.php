<?php

namespace App\Services\Inventory;

use App\Models\InventoryProduct;
use App\Models\InventoryPurchaseOrder;
use App\Models\InventoryPurchaseOrderItem;
use App\Models\InventoryWarehouse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Warehouse + purchase orders lite — receive PO into inventory_products stock.
 */
final class InventoryPurchaseService
{
    public function __construct(
        private readonly InventoryHubService $stock,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(string $filter = 'open'): array
    {
        $this->ensureDefaultWarehouse();

        $query = InventoryPurchaseOrder::query()
            ->with(['warehouse', 'items.product'])
            ->orderByDesc('id');

        match ($filter) {
            'received' => $query->where('status', 'received'),
            'cancelled' => $query->where('status', 'cancelled'),
            'all' => null,
            default => $query->whereIn('status', ['draft', 'ordered']),
        };

        $orders = $query->limit(100)->get()->map(fn (InventoryPurchaseOrder $po) => $this->orderRow($po))->all();

        return [
            'updated_at' => now()->toIso8601String(),
            'stats' => [
                'warehouses' => InventoryWarehouse::query()->where('is_active', true)->count(),
                'open_pos' => InventoryPurchaseOrder::query()->whereIn('status', ['draft', 'ordered'])->count(),
                'received_month' => InventoryPurchaseOrder::query()
                    ->where('status', 'received')
                    ->whereMonth('received_at', now()->month)
                    ->whereYear('received_at', now()->year)
                    ->count(),
                'open_value' => round((float) InventoryPurchaseOrder::query()
                    ->whereIn('status', ['draft', 'ordered'])
                    ->sum('total'), 2),
            ],
            'warehouses' => InventoryWarehouse::query()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
                ->map(fn (InventoryWarehouse $w) => [
                    'id' => $w->id,
                    'label' => $w->displayLabel(),
                    'name' => $w->name,
                    'code' => $w->code,
                    'is_default' => $w->is_default,
                    'is_active' => $w->is_active,
                    'address' => $w->address,
                ])
                ->all(),
            'products' => InventoryProduct::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(300)
                ->get(['id', 'name', 'sku', 'cost_price', 'stock_qty'])
                ->map(fn (InventoryProduct $p) => [
                    'id' => $p->id,
                    'label' => trim(($p->sku ? $p->sku.' — ' : '').$p->name),
                    'cost_price' => (float) $p->cost_price,
                    'stock_qty' => $p->stock_qty,
                ])
                ->all(),
            'orders' => $orders,
            'statuses' => InventoryPurchaseOrder::STATUSES,
        ];
    }

    /**
     * @param  array{vendor_name?: string, warehouse_id?: int|null, notes?: string, status?: string, items: list<array{product_id: int, quantity: int, unit_cost?: float}>}  $data
     */
    public function createOrder(array $data): InventoryPurchaseOrder
    {
        $items = $data['items'] ?? [];
        if ($items === []) {
            throw new InvalidArgumentException('Add at least one line item.');
        }

        return DB::transaction(function () use ($data, $items) {
            $po = InventoryPurchaseOrder::query()->create([
                'po_number' => $this->nextPoNumber(),
                'vendor_name' => $data['vendor_name'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? $this->defaultWarehouseId(),
                'status' => in_array(($data['status'] ?? 'draft'), ['draft', 'ordered'], true)
                    ? $data['status']
                    : 'draft',
                'total' => 0,
                'ordered_at' => ($data['status'] ?? '') === 'ordered' ? now()->toDateString() : null,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $total = 0.0;
            foreach ($items as $line) {
                $productId = (int) ($line['product_id'] ?? 0);
                $qty = max(1, (int) ($line['quantity'] ?? 1));
                if ($productId <= 0) {
                    continue;
                }
                $product = InventoryProduct::query()->findOrFail($productId);
                $unit = round((float) ($line['unit_cost'] ?? $product->cost_price), 2);
                $lineTotal = round($unit * $qty, 2);
                $total += $lineTotal;

                InventoryPurchaseOrderItem::query()->create([
                    'purchase_order_id' => $po->id,
                    'inventory_product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_cost' => $unit,
                    'line_total' => $lineTotal,
                ]);
            }

            if ($total <= 0) {
                throw new InvalidArgumentException('PO has no valid items.');
            }

            $po->update(['total' => $total]);

            return $po->fresh(['items.product', 'warehouse']);
        });
    }

    public function markOrdered(int $id): InventoryPurchaseOrder
    {
        $po = InventoryPurchaseOrder::query()->findOrFail($id);
        if ($po->status !== 'draft') {
            throw new InvalidArgumentException('Only draft POs can be marked ordered.');
        }
        $po->update([
            'status' => 'ordered',
            'ordered_at' => now()->toDateString(),
        ]);

        return $po->fresh();
    }

    public function receive(int $id): InventoryPurchaseOrder
    {
        $po = InventoryPurchaseOrder::query()->with('items.product')->findOrFail($id);
        if (! $po->canReceive()) {
            throw new InvalidArgumentException('PO cannot be received in status: '.$po->status);
        }
        if ($po->items->isEmpty()) {
            throw new InvalidArgumentException('PO has no items.');
        }

        return DB::transaction(function () use ($po) {
            foreach ($po->items as $item) {
                $this->stock->moveStock(
                    (int) $item->inventory_product_id,
                    'in',
                    (int) $item->quantity,
                    [
                        'unit_cost' => (float) $item->unit_cost,
                        'reference' => 'PO '.$po->po_number,
                        'notes' => 'Received purchase order',
                    ]
                );

                if ((float) $item->unit_cost > 0) {
                    InventoryProduct::query()->whereKey($item->inventory_product_id)->update([
                        'cost_price' => $item->unit_cost,
                    ]);
                }
            }

            $po->update([
                'status' => 'received',
                'received_at' => now()->toDateString(),
                'ordered_at' => $po->ordered_at ?? now()->toDateString(),
            ]);

            return $po->fresh(['items.product', 'warehouse']);
        });
    }

    public function cancel(int $id): InventoryPurchaseOrder
    {
        $po = InventoryPurchaseOrder::query()->findOrFail($id);
        if ($po->status === 'received') {
            throw new InvalidArgumentException('Received POs cannot be cancelled.');
        }
        $po->update(['status' => 'cancelled']);

        return $po->fresh();
    }

    /**
     * @param  array{code?: string, name: string, address?: string, is_default?: bool, notes?: string}  $data
     */
    public function saveWarehouse(?int $id, array $data): InventoryWarehouse
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Warehouse name is required.');
        }

        $payload = [
            'code' => filled($data['code'] ?? null) ? trim((string) $data['code']) : null,
            'name' => $name,
            'address' => $data['address'] ?? null,
            'is_default' => (bool) ($data['is_default'] ?? false),
            'is_active' => true,
            'notes' => $data['notes'] ?? null,
        ];

        return DB::transaction(function () use ($id, $payload) {
            if ($payload['is_default']) {
                InventoryWarehouse::query()->update(['is_default' => false]);
            }

            if ($id) {
                $wh = InventoryWarehouse::query()->findOrFail($id);
                $wh->update($payload);

                return $wh->fresh();
            }

            if (! InventoryWarehouse::query()->exists()) {
                $payload['is_default'] = true;
            }

            return InventoryWarehouse::query()->create($payload);
        });
    }

    public function ensureDefaultWarehouse(): InventoryWarehouse
    {
        $existing = InventoryWarehouse::query()->where('is_default', true)->first()
            ?? InventoryWarehouse::query()->first();

        if ($existing) {
            return $existing;
        }

        return InventoryWarehouse::query()->create([
            'code' => 'MAIN',
            'name' => 'Main Store',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    private function defaultWarehouseId(): ?int
    {
        return $this->ensureDefaultWarehouse()->id;
    }

    private function nextPoNumber(): string
    {
        $base = 'PO-'.now()->format('ymd');
        $seq = InventoryPurchaseOrder::query()->where('po_number', 'like', $base.'%')->count() + 1;

        return $base.'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT).'-'.Str::upper(Str::random(2));
    }

    /**
     * @return array<string, mixed>
     */
    private function orderRow(InventoryPurchaseOrder $po): array
    {
        return [
            'id' => $po->id,
            'po_number' => $po->po_number,
            'vendor_name' => $po->vendor_name,
            'warehouse' => $po->warehouse?->displayLabel(),
            'status' => $po->status,
            'status_label' => $po->status_label,
            'total' => (float) $po->total,
            'ordered_at' => optional($po->ordered_at)?->format('Y-m-d'),
            'received_at' => optional($po->received_at)?->format('Y-m-d'),
            'can_receive' => $po->canReceive(),
            'items' => $po->items->map(fn (InventoryPurchaseOrderItem $i) => [
                'product' => $i->product?->name ?? '—',
                'sku' => $i->product?->sku,
                'quantity' => $i->quantity,
                'unit_cost' => (float) $i->unit_cost,
                'line_total' => (float) $i->line_total,
            ])->all(),
        ];
    }
}
