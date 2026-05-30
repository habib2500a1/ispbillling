<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Services\Network\NetworkAccessCoordinator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerServiceExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_is_service_expired_after_valid_date(): void
    {
        $package = Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 100,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Exp',
            'phone' => '01700000001',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'network_access_state' => 'active',
            'service_expires_at' => now()->subDays(3)->toDateString(),
        ]);

        $this->assertTrue($customer->fresh()->isServiceExpired());
    }

    public function test_coordinator_demotes_expired_customer_when_enforced(): void
    {
        config([
            'network.service_expiry_enforced' => true,
            'network.auto_suspend_enabled' => false,
            'network.provisioner_driver' => 'null',
        ]);

        $package = Package::query()->create([
            'name' => 'P2',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 100,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Exp2',
            'phone' => '01700000002',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'network_access_state' => 'active',
            'service_expires_at' => now()->subDay()->toDateString(),
        ]);

        $provisioner = $this->createMock(\App\Contracts\NetworkAccessProvisioner::class);
        $provisioner->expects($this->once())->method('syncAccessPolicy');
        $this->app->instance(\App\Contracts\NetworkAccessProvisioner::class, $provisioner);

        app(NetworkAccessCoordinator::class)->syncCustomer($customer->fresh());

        $customer->refresh();
        $this->assertSame('expired', $customer->status);
        $this->assertSame('suspended', $customer->network_access_state);
    }
}
