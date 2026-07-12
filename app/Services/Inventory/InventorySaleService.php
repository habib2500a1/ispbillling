<?php

namespace App\Services\Inventory;

use App\Models\CustomersInfo;
use App\Models\InventoryProduct;
use App\Models\InventorySale;
use App\Models\InventorySaleItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Inventory sales / issue-to-customer lite — stocks out via InventoryHubService.
 */
final class InventorySaleService
{
    public function __construct(
        private readonly InventoryHubService $stock,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(string $filter = 'all'): array
    {
        $query = InventorySale::query()
            ->with(['items.product', 'recorder:id,name'])
            ->orderByDesc('sold_at');

        match ($filter) {
            'issue' => $query->whereIn('channel', ['issue', 'field']),
            'counter' => $query->where('channel', 'counter'),
            'month' => $query->whereMonth('sold_at', now()->month)->whereYear('sold_at', now()->year),
            default => null,
        };

        $sales = $query->limit(80)->get()->map(fn (InventorySale $s) => $this->saleRow($s))->all();

        $monthSales = InventorySale::query()
            ->where('status', 'completed')
            ->whereMonth('sold_at', now()->month)
            ->whereYear('sold_at', now()->year);

        return [
            'updated_at' => now()->toIso8601String(),
            'stats' => [
                'sales_month' => (clone $monthSales)->count(),
                'revenue_month' => round((float) (clone $monthSales)->sum('total'), 2),
                'profit_month' => round((float) (clone $monthSales)->sum('gross_profit'), 2),
                'issues_month' => InventorySale::query()
                    ->whereIn('channel', ['issue', 'field'])
                    ->where('status', 'completed')
                    ->whereMonth('sold_at', now()->month)
                    ->whereYear('sold_at', now()->year)
                    ->count(),
            ],
            'sales' => $sales,
            'products' => InventoryProduct::query()
                ->where('is_active', true)
                ->where('stock_qty', '>', 0)
                ->orderBy('name')
                ->limit(300)
                ->get(['id', 'name', 'sku', 'stock_qty', 'cost_price', 'sell_price'])
                ->map(fn (InventoryProduct $p) => [
                    'id' => $p->id,
                    'label' => trim(($p->sku ? $p->sku.' — ' : '').$p->name),
                    'stock_qty' => $p->stock_qty,
                    'cost_price' => (float) $p->cost_price,
                    'sell_price' => (float) $p->sell_price,
                ])
                ->all(),
            'channels' => InventorySale::CHANNELS,
        ];
    }

    /**
     * @return list<array{id: int, label: string, mobile: ?string, uid: string}>
     */
    public function searchCustomers(string $q, int $limit = 12): array
    {
        $q = trim($q);
        if (strlen($q) < 2) {
            return [];
        }

        return CustomersInfo::query()
            ->whereNull('deleted_at')
            ->where(function ($query) use ($q) {
                $query->where('customer_name', 'like', "%{$q}%")
                    ->orWhere('customer_unique_id', 'like', "%{$q}%")
                    ->orWhere('mobile', 'like', "%{$q}%");
            })
            ->orderBy('customer_name')
            ->limit($limit)
            ->get(['id', 'customer_unique_id', 'customer_name', 'mobile'])
            ->map(fn (CustomersInfo $c) => [
                'id' => $c->id,
                'uid' => $c->customer_unique_id,
                'label' => $c->customer_name.' ('.$c->customer_unique_id.')',
                'mobile' => $c->mobile,
            ])
            ->all();
    }

    /**
     * @param  array{
     *   channel?: string,
     *   customer_unique_id?: ?string,
     *   customer_name?: ?string,
     *   customer_phone?: ?string,
     *   discount?: float|int|string,
     *   payment_method?: string,
     *   notes?: ?string,
     *   items: list<array{product_id: int, quantity: int, unit_price?: float|int|string}>
     * }  $data
     */
    public function record(array $data): InventorySale
    {
        $items = $data['items'] ?? [];
        if ($items === []) {
            throw new InvalidArgumentException('Add at least one line.');
        }

        $channel = $data['channel'] ?? 'counter';
        if (! array_key_exists($channel, InventorySale::CHANNELS)) {
            $channel = 'counter';
        }

        $customerUid = $data['customer_unique_id'] ?? null;
        $customerName = $data['customer_name'] ?? null;
        $customerPhone = $data['customer_phone'] ?? null;

        if ($customerUid) {
            $customer = CustomersInfo::query()->where('customer_unique_id', $customerUid)->first();
            if ($customer) {
                $customerName = $customerName ?: $customer->customer_name;
                $customerPhone = $customerPhone ?: $customer->mobile;
            }
        }

        return DB::transaction(function () use ($data, $items, $channel, $customerUid, $customerName, $customerPhone) {
            $subtotal = 0.0;
            $totalCost = 0.0;
            $normalized = [];

            foreach ($items as $line) {
                $productId = (int) ($line['product_id'] ?? 0);
                $qty = max(1, (int) ($line['quantity'] ?? 1));
                if ($productId <= 0) {
                    continue;
                }
                $product = InventoryProduct::query()->lockForUpdate()->findOrFail($productId);
                if ($product->stock_qty < $qty) {
                    throw new InvalidArgumentException("Insufficient stock for {$product->name} (have {$product->stock_qty}).");
                }
                $unitPrice = round((float) ($line['unit_price'] ?? $product->sell_price), 2);
                if ($channel !== 'counter' && $unitPrice <= 0) {
                    $unitPrice = 0; // free issue allowed
                }
                $unitCost = (float) $product->cost_price;
                $lineTotal = round($unitPrice * $qty, 2);
                $subtotal += $lineTotal;
                $totalCost += round($unitCost * $qty, 2);
                $normalized[] = compact('product', 'qty', 'unitPrice', 'unitCost', 'lineTotal');
            }

            if ($normalized === []) {
                throw new InvalidArgumentException('No valid sale lines.');
            }

            $discount = max(0, round((float) ($data['discount'] ?? 0), 2));
            $total = max(0, round($subtotal - $discount, 2));
            $profit = round($total - $totalCost, 2);

            $sale = InventorySale::query()->create([
                'sale_number' => $this->nextSaleNumber(),
                'channel' => $channel,
                'customer_unique_id' => $customerUid,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'total_cost' => $totalCost,
                'gross_profit' => $profit,
                'payment_method' => $data['payment_method'] ?? ($channel === 'counter' ? 'cash' : 'n/a'),
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
                'recorded_by' => Auth::id(),
                'sold_at' => now(),
            ]);

            foreach ($normalized as $row) {
                /** @var InventoryProduct $product */
                $product = $row['product'];
                InventorySaleItem::query()->create([
                    'inventory_sale_id' => $sale->id,
                    'inventory_product_id' => $product->id,
                    'quantity' => $row['qty'],
                    'unit_cost' => $row['unitCost'],
                    'unit_price' => $row['unitPrice'],
                    'line_total' => $row['lineTotal'],
                ]);

                $this->stock->moveStock($product->id, 'out', $row['qty'], [
                    'unit_cost' => $row['unitCost'],
                    'reference' => $sale->sale_number,
                    'notes' => InventorySale::CHANNELS[$channel].' stock out',
                ]);
            }

            return $sale->fresh(['items.product']);
        });
    }

    private function nextSaleNumber(): string
    {
        $base = 'SL-'.now()->format('ymd');
        $seq = InventorySale::query()->where('sale_number', 'like', $base.'%')->count() + 1;

        return $base.'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT).'-'.Str::upper(Str::random(2));
    }

    /**
     * @return array<string, mixed>
     */
    private function saleRow(InventorySale $s): array
    {
        return [
            'id' => $s->id,
            'sale_number' => $s->sale_number,
            'channel' => $s->channel,
            'channel_label' => $s->channel_label,
            'customer_name' => $s->customer_name ?: ($s->customer_unique_id ?: '—'),
            'customer_unique_id' => $s->customer_unique_id,
            'phone' => $s->customer_phone,
            'total' => (float) $s->total,
            'profit' => (float) $s->gross_profit,
            'payment_method' => $s->payment_method,
            'sold_at' => optional($s->sold_at)?->format('Y-m-d H:i'),
            'staff' => $s->recorder?->name,
            'items' => $s->items->map(fn (InventorySaleItem $i) => [
                'product' => $i->product?->name ?? '—',
                'quantity' => $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'line_total' => (float) $i->line_total,
            ])->all(),
        ];
    }
}
