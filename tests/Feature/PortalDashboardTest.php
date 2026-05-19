<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Device;
use App\Models\OnuHealthScore;
use App\Models\Package;
use App\Services\Portal\CustomerPortalDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_live_endpoint_requires_auth(): void
    {
        $this->getJson(route('portal.dashboard.live'))->assertUnauthorized();
    }

    public function test_dashboard_shows_live_widgets(): void
    {
        $customer = $this->portalCustomer();

        $this->actingAs($customer, 'customer')
            ->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Live dashboard')
            ->assertSee('Connection');
    }

    public function test_onu_page_and_live_json(): void
    {
        $customer = $this->portalCustomer();
        $onu = Device::query()->create([
            'tenant_id' => 1,
            'type' => 'onu',
            'customer_id' => $customer->id,
            'serial_number' => 'ONU-PORTAL-1',
            'rx_power_dbm' => -18.5,
            'tx_power_dbm' => 2.1,
            'onu_oper_status' => 'online',
            'status' => 'assigned',
        ]);
        OnuHealthScore::query()->create([
            'tenant_id' => 1,
            'device_id' => $onu->id,
            'health_score' => 85,
            'stability_score' => 90,
            'smoothed_rx_dbm' => -18.5,
            'smoothed_tx_dbm' => 2.1,
            'fiber_health_score' => 88,
            'computed_at' => now(),
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('portal.onu.index'))
            ->assertOk()
            ->assertSee('-18.5');

        $this->actingAs($customer, 'customer')
            ->getJson(route('portal.onu.live'))
            ->assertOk()
            ->assertJsonPath('linked', true)
            ->assertJsonPath('rx_dbm', -18.5);
    }

    public function test_speed_test_routes(): void
    {
        $customer = $this->portalCustomer();

        $this->actingAs($customer, 'customer')
            ->get(route('portal.speed-test.index'))
            ->assertOk()
            ->assertSee('Speed test');

        $this->actingAs($customer, 'customer')
            ->getJson(route('portal.speed-test.ping'))
            ->assertOk();
    }

    public function test_dashboard_service_payload_structure(): void
    {
        $customer = $this->portalCustomer();
        $payload = app(CustomerPortalDashboardService::class)->payload($customer);

        $this->assertArrayHasKey('connection', $payload);
        $this->assertArrayHasKey('traffic', $payload);
        $this->assertArrayHasKey('onu', $payload);
        $this->assertArrayHasKey('billing', $payload);
    }

    private function portalCustomer(): Customer
    {
        $package = Package::query()->create([
            'name' => 'Portal 20',
            'type' => 'residential',
            'download_mbps' => 20,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
            'tenant_id' => 1,
        ]);

        return Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'C-PORTAL-1',
            'name' => 'Portal User',
            'phone' => '01700000001',
            'package_id' => $package->id,
            'status' => 'active',
            'portal_password' => Hash::make('secret'),
            'account_balance' => 100,
        ]);
    }
}
