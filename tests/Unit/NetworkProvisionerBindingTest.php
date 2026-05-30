<?php

namespace Tests\Unit;

use App\Contracts\NetworkAccessProvisioner;
use App\Services\Network\MikrotikNetworkProvisioner;
use App\Services\Network\NullNetworkProvisioner;
use Tests\TestCase;

class NetworkProvisionerBindingTest extends TestCase
{
    public function test_null_driver_with_always_push_resolves_mikrotik_provisioner(): void
    {
        config([
            'network.provisioner_driver' => 'null',
            'network.mikrotik_always_push_ppp_on_customer_save' => true,
            'network.mikrotik_push_enabled' => true,
        ]);

        $this->app->forgetInstance(NetworkAccessProvisioner::class);

        $provisioner = $this->app->make(NetworkAccessProvisioner::class);

        $this->assertInstanceOf(MikrotikNetworkProvisioner::class, $provisioner);
    }

    public function test_null_driver_without_always_push_resolves_null_provisioner(): void
    {
        config([
            'network.provisioner_driver' => 'null',
            'network.mikrotik_always_push_ppp_on_customer_save' => false,
            'network.mikrotik_push_enabled' => true,
        ]);

        $this->app->forgetInstance(NetworkAccessProvisioner::class);

        $provisioner = $this->app->make(NetworkAccessProvisioner::class);

        $this->assertInstanceOf(NullNetworkProvisioner::class, $provisioner);
    }
}
