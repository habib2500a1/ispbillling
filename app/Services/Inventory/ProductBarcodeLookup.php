<?php

namespace App\Services\Inventory;

use App\Models\Product;

final class ProductBarcodeLookup
{
    public function find(int $tenantId, string $scan): ?Product
    {
        $scan = trim($scan);
        if ($scan === '') {
            return null;
        }

        return Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($q) use ($scan): void {
                $q->where('barcode', $scan)
                    ->orWhere('sku', $scan);
            })
            ->first();
    }
}
