<?php

namespace Tests\Feature;

use App\Jobs\SyncCustomerNetworkAccessJob;
use App\Models\Customer;
use App\Models\MikrotikServer;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class CustomerNetworkSyncDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_customer_dispatches_sync_job_when_mikrotik_driver_enabled(): void
    {
        config([
            'network.provisioner_driver' => 'mikrotik',
            'network.mikrotik_push_enabled' => true,
            'network.mikrotik_always_push_ppp_on_customer_save' => true,
        ]);
        Bus::fake();

        MikrotikServer::query()->create([
            'tenant_id' => 1,
            'name' => 'mt1',
            'host' => '127.0.0.1',
            'api_port' => 8728,
            'use_ssl' => false,
            'legacy_login' => false,
            'api_username' => 'admin',
            'api_password' => 'admin',
            'is_enabled' => true,
            'last_api_status' => 'unknown',
        ]);

        $package = Package::query()->create([
            'name' => 'Plan',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 100,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        Customer::query()->create([
            'name' => 'PPPoE User',
            'phone' => '01800000001',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'network_access_state' => 'active',
            'mikrotik_ppp_password' => 'ppp-secret-1',
        ]);

        Bus::assertDispatched(SyncCustomerNetworkAccessJob::class);
    }

    public function test_saving_customer_dispatches_when_driver_null_but_always_push_and_enabled_mikrotik_server(): void
    {
        config([
            'network.provisioner_driver' => 'null',
            'network.mikrotik_push_enabled' => true,
            'network.mikrotik_always_push_ppp_on_customer_save' => true,
        ]);
        Bus::fake();

        MikrotikServer::query()->create([
            'tenant_id' => 1,
            'name' => 'mt-api',
            'host' => '127.0.0.1',
            'api_port' => 8728,
            'use_ssl' => false,
            'legacy_login' => false,
            'api_username' => 'admin',
            'api_password' => 'admin',
            'is_enabled' => true,
            'last_api_status' => 'unknown',
        ]);

        $package = Package::query()->create([
            'name' => 'Plan2',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 100,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        Customer::query()->create([
            'name' => 'API path',
            'phone' => '01800000002',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'network_access_state' => 'active',
        ]);

        Bus::assertDispatched(SyncCustomerNetworkAccessJob::class);
    }

    public function test_saving_customer_does_not_dispatch_when_driver_null_and_always_push_false(): void
    {
        config([
            'network.provisioner_driver' => 'null',
            'network.mikrotik_push_enabled' => true,
            'network.mikrotik_always_push_ppp_on_customer_save' => false,
        ]);
        Bus::fake();

        MikrotikServer::query()->create([
            'tenant_id' => 1,
            'name' => 'mt-off',
            'host' => '127.0.0.1',
            'api_port' => 8728,
            'use_ssl' => false,
            'legacy_login' => false,
            'api_username' => 'admin',
            'api_password' => 'admin',
            'is_enabled' => true,
            'last_api_status' => 'unknown',
        ]);

        $package = Package::query()->create([
            'name' => 'Plan3',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 100,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        Customer::query()->create([
            'name' => 'No dispatch',
            'phone' => '01800000003',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'network_access_state' => 'active',
        ]);

        Bus::assertNotDispatched(SyncCustomerNetworkAccessJob::class);
    }

    public function test_saving_customer_does_not_dispatch_when_no_enabled_mikrotik_server_even_if_always_push(): void
    {
        config([
            'network.provisioner_driver' => 'null',
            'network.mikrotik_push_enabled' => true,
            'network.mikrotik_always_push_ppp_on_customer_save' => true,
        ]);
        Bus::fake();

        $package = Package::query()->create([
            'name' => 'Plan4',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 100,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        Customer::query()->create([
            'name' => 'No MT server',
            'phone' => '01800000004',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'network_access_state' => 'active',
        ]);

        Bus::assertNotDispatched(SyncCustomerNetworkAccessJob::class);
    }
}
