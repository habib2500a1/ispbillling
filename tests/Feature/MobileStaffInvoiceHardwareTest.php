<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Inventory\InventoryStockService;
use App\Services\Inventory\WarehouseResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MobileStaffInvoiceHardwareTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_hardware_line_to_invoice_via_api(): void
    {
        Role::findOrCreate('super-admin');

        $tenantId = (int) (Tenant::query()->value('id') ?? 1);
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        $user->assignRole('super-admin');

        $customer = Customer::query()->create([
            'tenant_id' => $tenantId,
            'customer_code' => 'C-HW-'.uniqid(),
            'name' => 'Hardware Customer',
            'phone' => '01700000099',
            'status' => 'active',
        ]);
        $invoice = Invoice::createTrusted([
            'tenant_id' => $tenantId,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-HW-TEST-1',
            'issue_date' => now()->startOfMonth(),
            'due_date' => now()->addDays(7),
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => 'open',
            'subtotal' => 0,
            'total' => 0,
            'amount_paid' => 0,
        ]);

        $warehouseId = app(WarehouseResolver::class)->defaultWarehouseId($tenantId);
        $product = Product::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'sku' => 'ONU-X',
            'name' => 'ONU Device',
            'unit' => 'pcs',
            'sell_price' => 1200,
            'cost_price' => 800,
            'is_active' => true,
        ]);
        app(InventoryStockService::class)->adjustStock($product, 3, 'adjustment_in', 800, 0, null, null, 'seed', $user, $warehouseId);

        Sanctum::actingAs($user, ['staff']);

        $this->getJson("/api/v1/staff/invoices/{$invoice->id}/hardware-options")
            ->assertOk()
            ->assertJsonPath('invoice.id', $invoice->id);

        $this->postJson("/api/v1/staff/invoices/{$invoice->id}/hardware-line", [
            'product_id' => $product->id,
            'quantity' => 1,
            'issue_stock' => true,
            'warehouse_id' => $warehouseId,
        ])
            ->assertCreated()
            ->assertJsonPath('item.stock_issued', true);

        $invoice->refresh();
        $this->assertGreaterThan(0, (float) $invoice->total);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'item_type' => 'hardware',
        ]);
    }
}
