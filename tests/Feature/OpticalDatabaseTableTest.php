<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Device;
use App\Services\Optical\OpticalDatabasePresenter;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpticalDatabaseTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_database_row_matches_isp_digital_columns(): void
    {
        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => '424',
            'name' => 'Md Mehedi Hasan',
            'mikrotik_secret_name' => 'ak-mehedi',
            'phone' => '01700000000',
            'status' => 'active',
        ]);

        $olt = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'olt',
            'serial_number' => 'OLT-1',
            'display_name' => 'OLT',
            'status' => 'active',
        ]);

        $onu = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'onu',
            'serial_number' => 'ONU1',
            'mac_address' => '00:11:41:38:d1:e2',
            'olt_id' => $olt->id,
            'customer_id' => $customer->id,
            'rx_power_dbm' => -25.5284,
            'tx_power_dbm' => 2.1,
            'card_no' => 0,
            'pon_no' => 2,
            'onu_index' => 8,
            'onu_oper_status' => 'online',
            'status' => 'assigned',
            'last_polled_at' => now(),
        ]);

        $paginator = app(OpticalDatabasePresenter::class)->paginate(1, null, 25);
        $row = $paginator->items()[0];

        $this->assertSame('424', $row['client_code']);
        $this->assertSame('ak-mehedi', $row['username']);
        $this->assertSame('Md Mehedi Hasan', $row['client_name']);
        $this->assertEqualsWithDelta(-25.5284, (float) $row['optical_power'], 0.001);
        $this->assertSame('00:11:41:38:D1:E2', $row['onu_mac']);
        $this->assertSame('Online', $row['onu_status']);
    }
}
