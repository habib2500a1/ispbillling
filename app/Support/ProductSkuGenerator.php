<?php

namespace App\Support;

use App\Models\Product;

final class ProductSkuGenerator
{
    public static function generate(int $tenantId, ?string $name = null): string
    {
        $prefix = 'PRD-';
        $slug = strtoupper(preg_replace('/[^A-Z0-9]/', '', substr((string) $name, 0, 6)) ?: 'ITEM');

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $suffix = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $sku = $prefix.$slug.'-'.$suffix;

            if (! Product::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('sku', $sku)
                ->exists()) {
                return $sku;
            }
        }

        return $prefix.$slug.'-'.now()->format('His');
    }
}
