<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Device;
use App\Services\Optical\IspDigitalOnuAutoLinkService;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IspDigitalOnuAutoLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_links_onu_by_customer_onu_mac_meta(): void
    {
        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'C100',
            'name' => 'Test User',
            'mikrotik_secret_name' => 'test-user',
            'phone' => '01700000001',
            'status' => 'active',
            'meta' => ['onu_mac' => '00:11:41:00:7F:0A'],
        ]);

        $olt = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'olt',
            'serial_number' => 'OLT-T',
            'status' => 'active',
        ]);

        $onu = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'onu',
            'olt_id' => $olt->id,
            'serial_number' => '001141007F0A',
            'mac_address' => '00:11:41:00:7f:0a',
            'display_name' => 'EPON0/3:1',
            'status' => 'inventory',
            'rx_power_dbm' => -20.5,
        ]);

        $linked = app(IspDigitalOnuAutoLinkService::class)->linkCustomersWithOpticalHints(1);

        $this->assertGreaterThanOrEqual(1, $linked);
        $this->assertSame((int) $customer->id, (int) $onu->fresh()->customer_id);
    }
}
