<?php

namespace App\Filament\Support;

use App\Models\Product;
use App\Services\Inventory\WarehouseResolver;
use App\Support\TenantResolver;
use Illuminate\Support\Collection;

final class PosProductOptions
{
    /**
     * @return array<int, string> id => HTML label (use with Select::allowHtml())
     */
    public static function labels(?int $warehouseId = null): array
    {
        return self::eligibleProducts($warehouseId)
            ->mapWithKeys(fn (Product $product): array => [
                $product->id => view('filament.forms.components.pos-product-option', [
                    'product' => $product,
                    'warehouseStock' => app(WarehouseResolver::class)->stockAt($product, $warehouseId),
                ])->render(),
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, sku: ?string, barcode: ?string, image_url: ?string, initials: string, sell_price: float, warehouse_stock: int}>
     */
    public static function gridItems(?int $warehouseId = null, string $search = ''): array
    {
        $search = mb_strtolower(trim($search));
        $resolver = app(WarehouseResolver::class);
        $tenantId = TenantResolver::requiredTenantId();
        $resolvedWarehouseId = $resolver->resolveWarehouseId($tenantId, $warehouseId);

        return self::eligibleProducts($warehouseId)
            ->filter(function (Product $product) use ($search): bool {
                if ($search === '') {
                    return true;
                }

                $haystack = mb_strtolower(implode(' ', array_filter([
                    $product->name,
                    $product->sku,
                    $product->barcode,
                ])));

                return str_contains($haystack, $search);
            })
            ->take(48)
            ->map(function (Product $product) use ($resolvedWarehouseId, $resolver): array {
                return [
                    'id' => (int) $product->id,
                    'name' => (string) $product->name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'image_url' => $product->imageUrl(),
                    'initials' => mb_strtoupper(mb_substr((string) $product->name, 0, 2)) ?: '?',
                    'sell_price' => $product->effectiveSellPrice(),
                    'warehouse_stock' => $resolver->stockAt($product, $resolvedWarehouseId),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Product>
     */
    public static function eligibleProducts(?int $warehouseId = null): Collection
    {
        $tenantId = TenantResolver::requiredTenantId();
        $warehouseId = app(WarehouseResolver::class)->resolveWarehouseId($tenantId, $warehouseId);

        return Product::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(function (Product $product) use ($warehouseId): bool {
                return app(WarehouseResolver::class)->stockAt($product, $warehouseId) > 0
                    || (int) $product->stock_qty > 0;
            })
            ->values();
    }
}
