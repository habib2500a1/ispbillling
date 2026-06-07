<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class StaffMobileTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_suspend_customer_from_another_tenant(): void
    {
        $tenantA = Tenant::query()->create(['name' => 'ISP A', 'slug' => 'isp-a', 'is_active' => true]);
        $tenantB = Tenant::query()->create(['name' => 'ISP B', 'slug' => 'isp-b', 'is_active' => true]);

        $staff = User::factory()->create(['tenant_id' => $tenantA->id]);
        $staff->assignRole('cashier');

        $otherCustomer = Customer::query()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Other tenant client',
            'customer_code' => 'B-001',
            'status' => 'active',
        ]);

        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/staff/network/suspend', [
            'customer_id' => $otherCustomer->id,
            'reason' => 'test',
        ])->assertNotFound();
    }

    public function test_staff_cannot_view_customer_detail_from_another_tenant(): void
    {
        $tenantA = Tenant::query()->create(['name' => 'ISP A', 'slug' => 'isp-a-2', 'is_active' => true]);
        $tenantB = Tenant::query()->create(['name' => 'ISP B', 'slug' => 'isp-b-2', 'is_active' => true]);

        $staff = User::factory()->create(['tenant_id' => $tenantA->id]);
        $staff->assignRole('collector');

        $otherCustomer = Customer::query()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Hidden client',
            'customer_code' => 'B-002',
            'status' => 'active',
        ]);

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/staff/customers/'.$otherCustomer->id)
            ->assertNotFound();
    }
}
