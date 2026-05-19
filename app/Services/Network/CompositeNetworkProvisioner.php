<?php

namespace App\Services\Network;

use App\Contracts\NetworkAccessProvisioner;
use App\Models\Customer;
use App\Models\Device;

/**
 * Runs MikroTik RouterOS API and/or FreeRADIUS DB provisioning based on config/network.php.
 */
final class CompositeNetworkProvisioner implements NetworkAccessProvisioner
{
    public function __construct(
        private readonly NetworkAccessProvisioner $mikrotik,
        private readonly NetworkAccessProvisioner $radius,
    ) {}

    private function useMikrotik(): bool
    {
        $driver = (string) config('network.provisioner_driver', 'null');

        return in_array($driver, ['mikrotik', 'both'], true)
            && (bool) config('network.mikrotik_push_enabled', true);
    }

    private function useRadius(): bool
    {
        $driver = (string) config('network.provisioner_driver', 'null');

        return in_array($driver, ['radius', 'both'], true)
            && (bool) config('network.radius_push_enabled', true);
    }

    public function suspendCustomer(Customer $customer, string $reason): void
    {
        if ($this->useMikrotik()) {
            $this->mikrotik->suspendCustomer($customer, $reason);
        }
        if ($this->useRadius()) {
            $this->radius->suspendCustomer($customer, $reason);
        }
    }

    public function unsuspendCustomer(Customer $customer): void
    {
        if ($this->useMikrotik()) {
            $this->mikrotik->unsuspendCustomer($customer);
        }
        if ($this->useRadius()) {
            $this->radius->unsuspendCustomer($customer);
        }
    }

    public function syncAccessPolicy(Customer $customer): void
    {
        if ($this->useMikrotik()) {
            $this->mikrotik->syncAccessPolicy($customer);
        }
        if ($this->useRadius()) {
            $this->radius->syncAccessPolicy($customer);
        }
    }

    public function pushOnuRuntimeState(Device $onu): void
    {
        if ($this->useMikrotik()) {
            $this->mikrotik->pushOnuRuntimeState($onu);
        }
        if ($this->useRadius()) {
            $this->radius->pushOnuRuntimeState($onu);
        }
    }
}
