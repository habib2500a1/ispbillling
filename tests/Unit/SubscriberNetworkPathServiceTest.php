<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\PppSessionLog;
use App\Services\Network\SubscriberNetworkPathService;
use App\Support\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class SubscriberNetworkPathServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_path_from_mikrotik_session_and_meta(): void
    {
        TenantResolver::fake(1);

        $server = MikrotikServer::query()->create([
            'tenant_id' => 1,
            'name' => 'Core-1',
            'host' => '10.10.10.1',
            'api_username' => 'api',
            'api_password' => 'secret',
            'is_enabled' => true,
        ]);

        $package = Package::query()->create([
            'tenant_id' => 1,
            'name' => '10M',
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
            'name' => 'Path User',
            'phone' => '01700001111',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'customer_code' => 'P70001',
            'mikrotik_server_id' => $server->id,
            'mikrotik_secret_name' => 'P70001',
            'meta' => [
                'home_router_ip' => '192.168.1.1',
                'home_router_user' => 'admin',
                'home_router_password_enc' => Crypt::encryptString('wifi-secret'),
            ],
        ]);

        PppSessionLog::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'mikrotik_server_id' => $server->id,
            'session_key' => 'sess-1',
            'username' => 'P70001',
            'framed_ip' => '103.25.10.50',
            'caller_id' => '00:11:22:33:44:55',
            'status' => 'active',
            'started_at' => now(),
        ]);

        $path = app(SubscriberNetworkPathService::class)->path($customer->fresh());

        $this->assertSame('10.10.10.1', $path['mikrotik']['host']);
        $this->assertSame('103.25.10.50', $path['ppp']['framed_ip']);
        $this->assertSame('wifi-secret', $path['home_router']['password']);
        $this->assertTrue($path['one_click_router']['available']);
        $this->assertSame('http://103.25.10.50', $path['one_click_router']['url']);
        $this->assertSame('wan', $path['one_click_router']['via']);
        $this->assertStringContainsString('MT 10.10.10.1', $path['path_label']);
    }
}
