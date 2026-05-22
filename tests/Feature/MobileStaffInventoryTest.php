<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Inventory\InventoryStockService;
use App\Services\Inventory\WarehouseResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MobileStaffInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_bootstrap_and_sale(): void
    {
        Permission::findOrCreate('inventory.view');
        $role = Role::findOrCreate('inventory-clerk');
        $role->givePermissionTo('inventory.view');

        $tenant = Tenant::query()->create(['name' => 'Inv Mobile', 'slug' => 'inv-mob-'.uniqid(), 'is_active' => true]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole($role);

        $warehouseId = app(WarehouseResolver::class)->defaultWarehouseId((int) $tenant->id);

        $product = Product::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'sku' => 'MOB-1',
            'barcode' => '111222333',
            'name' => 'Mobile Test ONU',
            'unit' => 'pcs',
            'unit_price' => 500,
            'sell_price' => 800,
            'cost_price' => 400,
            'stock_qty' => 0,
            'is_active' => true,
        ]);

        app(InventoryStockService::class)->adjustStock($product, 5, 'adjustment_in', 400, 0, null, null, 'seed', $user, $warehouseId);

        Sanctum::actingAs($user, ['staff']);

        $this->getJson('/api/v1/staff/inventory/bootstrap')
            ->assertOk()
            ->assertJsonPath('default_warehouse_id', $warehouseId)
            ->assertJsonStructure(['warehouses', 'summary', 'payment_methods']);

        $this->getJson('/api/v1/staff/inventory/products?barcode=111222333')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Mobile Test ONU');

        $this->postJson('/api/v1/staff/inventory/sales', [
            'warehouse_id' => $warehouseId,
            'payment_method' => 'cash',
            'lines' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 800],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('sale.total', 1600);

        $product->refresh();
        $this->assertSame(3, (int) $product->stock_qty);
    }

    public function test_dashboard_includes_inventory_module_for_permitted_user(): void
    {
        Permission::findOrCreate('inventory.view');
        $role = Role::findOrCreate('inventory-clerk');
        $role->givePermissionTo('inventory.view');

        $user = User::factory()->create();
        $user->assignRole($role);

        Sanctum::actingAs($user, ['staff']);

        $keys = collect($this->getJson('/api/v1/staff/dashboard')->json('app_modules'))->pluck('key');

        $this->assertTrue($keys->contains('inventory'));
    }
}
