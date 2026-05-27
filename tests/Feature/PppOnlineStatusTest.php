<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MikrotikServer;
use App\Models\PppSessionLog;
use App\Services\Bandwidth\BandwidthCollectionService;
use App\Services\Bandwidth\BandwidthSyncStatus;
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

    public function test_collect_marks_offline_when_mikrotik_api_unreachable(): void
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
            'last_api_status' => 'offline',
        ]);

        $result = app(BandwidthCollectionService::class)->collectForTenant(1);

        $this->assertFalse($result['api_ok']);
        $customer->refresh();
        $this->assertTrue($customer->is_ppp_online);
    }

    public function test_collect_marks_offline_when_all_mikrotik_disabled(): void
    {
        $customer = Customer::createTrusted([
            'tenant_id' => 1,
            'customer_code' => 'offline_user_1',
            'mikrotik_secret_name' => 'offline_user_1',
            'name' => 'Was Online',
            'phone' => '01733333333',
            'status' => 'active',
            'is_ppp_online' => true,
        ]);

        MikrotikServer::query()->create([
            'tenant_id' => 1,
            'name' => 'Disabled NAS',
            'host' => '127.0.0.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'is_enabled' => false,
        ]);

        app(BandwidthCollectionService::class)->collectForTenant(1);

        $customer->refresh();
        $this->assertFalse($customer->is_ppp_online);
    }

    public function test_normalize_strips_realm_suffix(): void
    {
        $this->assertSame('user1', CustomerPppLoginResolver::normalize('user1@realm'));
    }

    public function test_clear_stale_skips_when_fresh_bandwidth_sync_has_active_sessions(): void
    {
        $customer = Customer::createTrusted([
            'tenant_id' => 1,
            'customer_code' => 'live_user_1',
            'mikrotik_secret_name' => 'live_user_1',
            'name' => 'Live User',
            'phone' => '01755555555',
            'status' => 'active',
            'is_ppp_online' => true,
        ]);

        MikrotikServer::query()->create([
            'tenant_id' => 1,
            'name' => 'Probe Offline NAS',
            'host' => '127.0.0.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'is_enabled' => true,
            'last_api_status' => 'offline',
        ]);

        PppSessionLog::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'session_key' => 'srv1:live_user_1',
            'username' => 'live_user_1',
            'status' => 'active',
            'started_at' => now(),
        ]);

        BandwidthSyncStatus::store(1, [
            'api' => ['ok' => true, 'reachable' => true, 'sessions' => 12],
            'radius' => ['ok' => false, 'sessions' => 0],
            'merged_active' => 12,
        ]);

        app(BandwidthCollectionService::class)->clearStaleOnlineFlagsWhenRoutersUnreachable(1);

        $customer->refresh();
        $this->assertTrue($customer->is_ppp_online);
        $this->assertSame(1, PppSessionLog::query()->where('status', 'active')->count());
    }

    public function test_poll_disabled_shows_offline_despite_stale_db_flags(): void
    {
        config([
            'mikrotik.poll_enabled' => false,
            'bandwidth.collection_enabled' => false,
        ]);

        $customer = Customer::createTrusted([
            'tenant_id' => 1,
            'customer_code' => 'stale_online_1',
            'mikrotik_secret_name' => 'stale_online_1',
            'name' => 'Stale Online',
            'phone' => '01744444444',
            'status' => 'active',
            'is_ppp_online' => true,
        ]);

        MikrotikServer::query()->create([
            'tenant_id' => 1,
            'name' => 'Stale NAS',
            'host' => '127.0.0.1',
            'api_port' => 8728,
            'api_username' => 'admin',
            'api_password' => 'secret',
            'is_enabled' => true,
            'last_api_status' => 'online',
        ]);

        $this->assertFalse($customer->isPppOnline());

        app(BandwidthCollectionService::class)->refreshOnlineFlagsForTenant(1);

        $customer->refresh();
        $this->assertFalse($customer->is_ppp_online);
        $this->assertFalse($customer->isPppOnline());
    }
}
