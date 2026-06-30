<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Models\PppSessionLog;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouterHomePortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_router_home_page_loads(): void
    {
        config(['portal.router_home.enabled' => true]);

        $this->get('/router')
            ->assertOk()
            ->assertSee('Home router mini portal');
    }

    public function test_identifies_customer_by_public_ip(): void
    {
        config(['portal.router_home.enabled' => true]);
        TenantResolver::fake(1);

        $package = Package::query()->create([
            'tenant_id' => 1,
            'name' => '10 Mbps',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Router User',
            'phone' => '01712344521',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'customer_code' => 'R90001',
        ]);

        PppSessionLog::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'session_key' => 'test-session',
            'username' => 'R90001',
            'framed_ip' => '203.0.113.50',
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
            ->get('/router')
            ->assertOk()
            ->assertSee('R90001')
            ->assertSee('Router User');
    }

    public function test_manual_identify_by_code_and_phone(): void
    {
        config(['portal.router_home.enabled' => true]);
        TenantResolver::fake(1);

        $package = Package::query()->create([
            'tenant_id' => 1,
            'name' => '10 Mbps',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        Customer::query()->create([
            'tenant_id' => 1,
            'name' => 'Manual ID',
            'phone' => '01899994521',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'customer_code' => 'M80001',
        ]);

        $this->post('/router/identify', [
            'customer_code' => 'M80001',
            'phone_tail' => '4521',
        ])->assertOk()->assertSee('Manual ID');
    }
}
