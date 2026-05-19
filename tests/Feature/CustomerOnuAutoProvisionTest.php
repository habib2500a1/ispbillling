<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Device;
use App\Services\Optical\CustomerOnuAutoProvisionService;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOnuAutoProvisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
        config(['optical.auto_provision_customer_onu' => false]);
    }

    public function test_auto_creates_onu_for_customer_without_device(): void
    {
        config(['optical.auto_provision_customer_onu' => true]);
        $olt = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'olt',
            'serial_number' => 'OLT-AUTO-1',
            'status' => 'active',
        ]);
        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'CUST-100',
            'name' => 'Test User',
            'phone' => '01700000001',
            'mikrotik_secret_name' => 'user100',
            'status' => 'active',
        ]);

        $onu = app(CustomerOnuAutoProvisionService::class)->ensureForCustomer($customer);

        $this->assertNotNull($onu);
        $this->assertSame('onu', $onu->type);
        $this->assertSame($customer->id, $onu->customer_id);
        $this->assertSame($olt->id, $onu->olt_id);
    }

    public function test_links_orphan_onu_by_ppp_login(): void
    {
        Device::query()->create([
            'tenant_id' => 1,
            'type' => 'olt',
            'serial_number' => 'OLT-AUTO-2',
            'status' => 'active',
        ]);
        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'CUST-101',
            'name' => 'PPP User',
            'phone' => '01700000002',
            'mikrotik_secret_name' => 'pppuser1',
            'status' => 'active',
        ]);
        $orphan = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'onu',
            'serial_number' => 'pppuser1',
            'status' => 'assigned',
        ]);

        $onu = app(CustomerOnuAutoProvisionService::class)->ensureForCustomer($customer);

        $this->assertSame($orphan->id, $onu?->id);
        $this->assertSame($customer->id, $onu->fresh()->customer_id);
    }
}
