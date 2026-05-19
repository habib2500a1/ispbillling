<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MikrotikServer;
use App\Services\Bandwidth\BandwidthCollectionService;
use App\Support\CustomerPppLoginResolver;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PppOnlineStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_resolver_links_missing_secret_name_on_match(): void
    {
        $customer = Customer::query()->create([
            'tenant_id' => 1,
            'customer_code' => 'CUST-2605-0099',
            'name' => 'Link Test',
            'phone' => '01711111111',
            'status' => 'active',
        ]);

        $found = CustomerPppLoginResolver::resolve(1, 'router_login_xyz');

        $this->assertNull($found);

        $customer->update(['mikrotik_secret_name' => 'router_login_xyz']);

        $found = CustomerPppLoginResolver::resolve(1, 'router_login_xyz');

        $this->assertNotNull($found);
        $this->assertSame($customer->id, $found->id);
    }

    public function test_collect_keeps_online_flag_when_mikrotik_api_unreachable(): void
    {
        $customer = Customer::createTrusted([
            'tenant_id' => 1,
            'customer_code' => 'online_user_1',
            'mikrotik_secret_name' => 'online_user_1',
            'name' => 'Online',
            'phone' => '01722222222',
            'status' => 'active',
            'is_ppp_online' => true,
        ]);

        MikrotikServer::query()->create([
            'tenant_id' => 1,
            'name' => 'Test NAS',
            'host' => '127.0.0.1',
            'api_port' => 1,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'is_enabled' => true,
        ]);

        $result = app(BandwidthCollectionService::class)->collectForTenant(1);

        $this->assertFalse($result['api_ok']);
        $customer->refresh();
        $this->assertTrue($customer->is_ppp_online);
    }

    public function test_normalize_strips_realm_suffix(): void
    {
        $this->assertSame('user1', CustomerPppLoginResolver::normalize('user1@realm'));
    }
}
