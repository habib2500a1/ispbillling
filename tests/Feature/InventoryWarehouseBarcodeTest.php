<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\Inventory\ProductBarcodeLookup;
use App\Services\Inventory\WarehouseResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryWarehouseBarcodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_warehouse_is_created(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Inv WH', 'slug' => 'inv-wh-'.uniqid(), 'is_active' => true]);

        $warehouse = app(WarehouseResolver::class)->defaultWarehouse((int) $tenant->id);

        $this->assertSame('MAIN', $warehouse->code);
        $this->assertTrue($warehouse->is_default);
    }

    public function test_barcode_lookup_finds_product(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Inv WH', 'slug' => 'inv-wh-'.uniqid(), 'is_active' => true]);
        $product = Product::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'SKU-1',
            'barcode' => '8901234567890',
            'name' => 'ONU Router',
            'unit' => 'pcs',
            'unit_price' => 1000,
            'is_active' => true,
        ]);

        $found = app(ProductBarcodeLookup::class)->find((int) $tenant->id, '8901234567890');

        $this->assertNotNull($found);
        $this->assertSame($product->id, $found->id);

        $bySku = app(ProductBarcodeLookup::class)->find((int) $tenant->id, 'SKU-1');
        $this->assertSame($product->id, $bySku?->id);
    }

    public function test_warehouse_has_stock_levels_relation(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Inv WH', 'slug' => 'inv-wh-'.uniqid(), 'is_active' => true]);
        $warehouse = Warehouse::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'code' => 'BR1',
            'name' => 'Branch 1',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->assertSame('BR1 — Branch 1', $warehouse->displayLabel());
    }
}
