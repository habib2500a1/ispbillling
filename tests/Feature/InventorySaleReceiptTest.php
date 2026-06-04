<?php

namespace Tests\Feature;

use App\Models\InventorySale;
use App\Models\InventorySaleItem;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventorySaleReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_sale_receipt(): void
    {
        $sale = $this->makeSale();

        $this->get(route('inventory-sales.receipt', $sale))
            ->assertRedirect();
    }

    public function test_staff_with_inventory_permission_can_view_receipt(): void
    {
        Permission::findOrCreate('inventory.view');
        $role = Role::findOrCreate('inventory-clerk');
        $role->givePermissionTo('inventory.view');

        $tenant = Tenant::query()->create(['name' => 'Receipt Co', 'slug' => 'rcp-'.uniqid(), 'is_active' => true]);
        TenantResolver::fake($tenant->id);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole($role);

        $sale = $this->makeSale($tenant->id);

        $this->actingAs($user)
            ->get(route('inventory-sales.receipt', $sale))
            ->assertOk()
            ->assertSee($sale->sale_number)
            ->assertSee('Retail sale receipt');
    }

    public function test_staff_can_download_pdf_receipt(): void
    {
        Permission::findOrCreate('inventory.view');
        $role = Role::findOrCreate('inventory-clerk');
        $role->givePermissionTo('inventory.view');

        $tenant = Tenant::query()->create(['name' => 'PDF Co', 'slug' => 'pdf-'.uniqid(), 'is_active' => true]);
        TenantResolver::fake($tenant->id);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole($role);

        $sale = $this->makeSale($tenant->id);

        $response = $this->actingAs($user)->get(route('inventory-sales.receipt.pdf', $sale));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    private function makeSale(?int $tenantId = null): InventorySale
    {
        $tenantId = $tenantId ?? Tenant::query()->create([
            'name' => 'T',
            'slug' => 't-'.uniqid(),
            'is_active' => true,
        ])->id;

        $sale = InventorySale::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'sale_number' => 'SAL-TEST-0001',
            'channel' => 'counter',
            'subtotal' => 1000,
            'discount' => 0,
            'total' => 1000,
            'total_cost' => 600,
            'gross_profit' => 400,
            'payment_method' => 'cash',
            'status' => 'completed',
            'sold_at' => now(),
        ]);

        InventorySaleItem::create([
            'inventory_sale_id' => $sale->id,
            'description' => 'Test ONU',
            'quantity' => 1,
            'unit_cost' => 600,
            'unit_price' => 1000,
            'line_total' => 1000,
            'line_cost' => 600,
            'line_profit' => 400,
        ]);

        return $sale;
    }
}
