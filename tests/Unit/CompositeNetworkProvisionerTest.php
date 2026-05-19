<?php

namespace Tests\Unit;

use App\Contracts\NetworkAccessProvisioner;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Package;
use App\Services\Network\CompositeNetworkProvisioner;
use App\Services\Network\MikrotikNetworkProvisioner;
use App\Services\Network\RadiusNetworkProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CountingProvisioner implements NetworkAccessProvisioner
{
    public int $suspendCount = 0;

    public int $unsuspendCount = 0;

    public int $syncCount = 0;

    public int $onuCount = 0;

    public function suspendCustomer(Customer $customer, string $reason): void
    {
        $this->suspendCount++;
    }

    public function unsuspendCustomer(Customer $customer): void
    {
        $this->unsuspendCount++;
    }

    public function syncAccessPolicy(Customer $customer): void
    {
        $this->syncCount++;
    }

    public function pushOnuRuntimeState(Device $onu): void
    {
        $this->onuCount++;
    }
}

class CompositeNetworkProvisionerTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_driver_respects_mikrotik_toggle(): void
    {
        config([
            'network.provisioner_driver' => 'both',
            'network.mikrotik_push_enabled' => true,
            'network.radius_push_enabled' => false,
        ]);

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
            'name' => 'C',
            'phone' => '01500000001',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
        ]);

        $m = new CountingProvisioner;
        $r = new CountingProvisioner;
        $p = new CompositeNetworkProvisioner($m, $r);
        $p->suspendCustomer($customer, 'test');

        $this->assertSame(1, $m->suspendCount);
        $this->assertSame(0, $r->suspendCount);
    }

    public function test_radius_only_driver_runs_radius_when_toggle_on(): void
    {
        config([
            'network.provisioner_driver' => 'radius',
            'network.mikrotik_push_enabled' => true,
            'network.radius_push_enabled' => true,
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
            'name' => 'C2',
            'phone' => '01500000002',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
        ]);

        $m = new CountingProvisioner;
        $r = new CountingProvisioner;
        $p = new CompositeNetworkProvisioner($m, $r);
        $p->suspendCustomer($customer, 'x');

        $this->assertSame(0, $m->suspendCount);
        $this->assertSame(1, $r->suspendCount);
    }

    public function test_app_service_provider_wires_composite_for_both_driver(): void
    {
        config([
            'network.provisioner_driver' => 'both',
            'network.mikrotik_push_enabled' => true,
            'network.radius_push_enabled' => true,
        ]);

        $this->assertInstanceOf(
            CompositeNetworkProvisioner::class,
            app(\App\Contracts\NetworkAccessProvisioner::class),
        );
    }
}
