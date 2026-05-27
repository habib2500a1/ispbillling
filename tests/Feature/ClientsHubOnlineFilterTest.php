<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PppSessionLog;
use App\Services\Bandwidth\BandwidthCollectionService;
use App\Support\CustomerStatus;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientsHubOnlineFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantResolver::fake(1);
    }

    public function test_online_filter_falls_back_to_active_sessions_when_sync_cache_is_stale(): void
    {
        $online = Customer::createTrusted([
            'tenant_id' => 1,
            'customer_code' => 'online_user_1',
            'mikrotik_secret_name' => 'online_user_1',
            'name' => 'Online User',
            'phone' => '01711111111',
            'status' => CustomerStatus::ACTIVE,
            'is_ppp_online' => true,
        ]);

        Customer::createTrusted([
            'tenant_id' => 1,
            'customer_code' => 'offline_user_1',
            'mikrotik_secret_name' => 'offline_user_1',
            'name' => 'Offline User',
            'phone' => '01722222222',
            'status' => CustomerStatus::ACTIVE,
            'is_ppp_online' => false,
        ]);

        PppSessionLog::query()->create([
            'tenant_id' => 1,
            'customer_id' => $online->id,
            'session_key' => 'srv1:online_user_1',
            'username' => 'online_user_1',
            'status' => 'active',
            'started_at' => now(),
        ]);

        $bandwidth = app(BandwidthCollectionService::class);
        $query = Customer::query()->where('tenant_id', 1);

        $this->assertFalse($bandwidth->tenantOnlineFlagsTrustworthy(1));
        $this->assertSame(1, $bandwidth->applyDisplayedOnlineFilter($query, 1, true)->count());
        $this->assertSame(1, $bandwidth->displayedOnlineCount(1));
    }
}
