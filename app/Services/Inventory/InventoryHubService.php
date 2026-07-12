<?php

namespace App\Services\Inventory;

use App\Models\InventoryProduct;
use App\Models\InventoryStockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Inventory lite for Code Pagol — products + stock movements (no warehouses).
 */
final class InventoryHubService
{
    /**
     * @return array<string, mixed>
     */
    public function payload(?string $search = null, string $filter = 'all'): array
    {
        $query = InventoryProduct::query()->orderBy('name');

        if ($search) {
            $s = trim($search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('sku', 'like', "%{$s}%")
                    ->orWhere('category', 'like', "%{$s}%");
            });
        }

        match ($filter) {
            'low' => $query->where('is_active', true)
                ->whereColumn('stock_qty', '<=', 'reorder_level')
                ->where('reorder_level', '>', 0),
            'inactive' => $query->where('is_active', false),
            'active' => $query->where('is_active', true),
            default => null,
        };

        $products = $query->limit(200)->get();

        $active = InventoryProduct::query()->where('is_active', true);
        $stockValue = 0.0;
        $lowStock = 0;
        (clone $active)->get()->each(function (InventoryProduct $p) use (&$stockValue, &$lowStock) {
            $stockValue += $p->stockValue();
            if ($p->isLowStock()) {
                $lowStock++;
            }
        });

        $recent = InventoryStockMovement::query()
            ->with(['product:id,name,sku', 'staff:id,name'])
            ->orderByDesc('moved_at')
            ->limit(20)
            ->get()
            ->map(fn (InventoryStockMovement $m) => [
                'id' => $m->id,
                'product' => $m->product?->name ?? '—',
                'sku' => $m->product?->sku,
                'type' => $m->type,
                'type_label' => $m->type_label,
                'quantity' => $m->quantity,
                'stock_after' => $m->stock_after,
                'reference' => $m->reference,
                'staff' => $m->staff?->name,
                'moved_at' => optional($m->moved_at)?->format('Y-m-d H:i'),
                'moved_human' => optional($m->moved_at)?->diffForHumans(),
            ])
            ->all();

        return [
            'updated_at' => now()->toIso8601String(),
            'stats' => [
                'products' => (clone $active)->count(),
                'stock_units' => (int) (clone $active)->sum('stock_qty'),
                'stock_value' => round($stockValue, 2),
                'low_stock' => $lowStock,
                'movements_today' => InventoryStockMovement::query()->whereDate('moved_at', today())->count(),
            ],
            'products' => $products->map(fn (InventoryProduct $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'category' => $p->category,
                'category_label' => $p->category_label,
                'unit' => $p->unit,
                'stock_qty' => $p->stock_qty,
                'reorder_level' => $p->reorder_level,
                'cost_price' => (float) $p->cost_price,
                'sell_price' => (float) $p->sell_price,
                'stock_value' => $p->stockValue(),
                'is_active' => $p->is_active,
                'is_low' => $p->isLowStock(),
                'notes' => $p->notes,
            ])->all(),
            'recent_movements' => $recent,
            'categories' => InventoryProduct::CATEGORIES,
            'movement_types' => InventoryStockMovement::TYPES,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveProduct(?int $id, array $data): InventoryProduct
    {
        $payload = [
            'sku' => filled($data['sku'] ?? null) ? trim((string) $data['sku']) : null,
            'name' => trim((string) ($data['name'] ?? '')),
            'category' => filled($data['category'] ?? null) ? (string) $data['category'] : 'other',
            'unit' => filled($data['unit'] ?? null) ? (string) $data['unit'] : 'pcs',
            'reorder_level' => max(0, (int) ($data['reorder_level'] ?? 0)),
            'cost_price' => round((float) ($data['cost_price'] ?? 0), 2),
            'sell_price' => round((float) ($data['sell_price'] ?? 0), 2),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'notes' => $data['notes'] ?? null,
        ];

        if ($payload['name'] === '') {
            throw new InvalidArgumentException('Product name is required.');
        }

        if ($id) {
            $product = InventoryProduct::query()->findOrFail($id);
            $product->update($payload);

            return $product->fresh();
        }

        $payload['stock_qty'] = max(0, (int) ($data['stock_qty'] ?? 0));
        $product = InventoryProduct::query()->create($payload);

        if ($payload['stock_qty'] > 0) {
            $this->recordMovement($product, 'in', $payload['stock_qty'], [
                'unit_cost' => $payload['cost_price'],
                'reference' => 'Opening stock',
                'notes' => 'Initial stock on create',
            ]);
        }

        return $product->fresh();
    }

    /**
     * @param  array{unit_cost?: float|int|string, reference?: string, notes?: string}  $meta
     */
    public function moveStock(int $productId, string $type, int $qty, array $meta = []): InventoryStockMovement
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        if (! array_key_exists($type, InventoryStockMovement::TYPES)) {
            throw new InvalidArgumentException('Invalid movement type.');
        }

        return DB::transaction(function () use ($productId, $type, $qty, $meta) {
            /** @var InventoryProduct $product */
            $product = InventoryProduct::query()->lockForUpdate()->findOrFail($productId);

            $delta = match ($type) {
                'in' => $qty,
                'out' => -$qty,
                'adjust' => $qty - (int) $product->stock_qty, // set absolute via qty as new stock
                default => throw new InvalidArgumentException('Invalid movement type.'),
            };

            // For adjust, $qty is the new absolute stock level
            if ($type === 'adjust') {
                $newStock = max(0, $qty);
                $delta = $newStock - (int) $product->stock_qty;
                if ($delta === 0) {
                    throw new InvalidArgumentException('Stock already at that quantity.');
                }
            } else {
                $newStock = (int) $product->stock_qty + $delta;
                if ($newStock < 0) {
                    throw new InvalidArgumentException('Insufficient stock.');
                }
            }

            $product->update(['stock_qty' => $newStock]);

            return InventoryStockMovement::query()->create([
                'inventory_product_id' => $product->id,
                'type' => $type,
                'quantity' => $delta,
                'stock_after' => $newStock,
                'unit_cost' => round((float) ($meta['unit_cost'] ?? $product->cost_price), 2),
                'reference' => $meta['reference'] ?? null,
                'notes' => $meta['notes'] ?? null,
                'staff_user_id' => Auth::id(),
                'moved_at' => now(),
            ]);
        });
    }

    /**
     * @param  array{unit_cost?: float|int|string, reference?: string, notes?: string}  $meta
     */
    private function recordMovement(InventoryProduct $product, string $type, int $absoluteQtyForIn, array $meta): void
    {
        InventoryStockMovement::query()->create([
            'inventory_product_id' => $product->id,
            'type' => $type,
            'quantity' => $absoluteQtyForIn,
            'stock_after' => $product->stock_qty,
            'unit_cost' => round((float) ($meta['unit_cost'] ?? $product->cost_price), 2),
            'reference' => $meta['reference'] ?? null,
            'notes' => $meta['notes'] ?? null,
            'staff_user_id' => Auth::id(),
            'moved_at' => now(),
        ]);
    }

    public function toggleActive(int $id): InventoryProduct
    {
        $product = InventoryProduct::query()->findOrFail($id);
        $product->update(['is_active' => ! $product->is_active]);

        return $product->fresh();
    }
}
