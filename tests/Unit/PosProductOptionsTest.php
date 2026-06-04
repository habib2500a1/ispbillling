<?php

namespace Tests\Unit;

use App\Filament\Support\PosProductOptions;
use App\Models\Product;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosProductOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_grid_items_filters_by_search(): void
    {
        $tenantId = TenantResolver::requiredTenantId();

        Product::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'name' => 'Fiber Patch Cord',
            'sku' => 'FPC-01',
            'stock_qty' => 5,
            'is_active' => true,
        ]);

        Product::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'name' => 'ONU Router',
            'sku' => 'ONU-99',
            'stock_qty' => 2,
            'is_active' => true,
        ]);

        $items = PosProductOptions::gridItems(null, 'patch');

        $this->assertCount(1, $items);
        $this->assertSame('Fiber Patch Cord', $items[0]['name']);
    }
}
