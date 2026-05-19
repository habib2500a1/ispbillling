<?php

namespace App\Services\Network;

use App\Contracts\NetworkAccessProvisioner;
use App\Models\Customer;
use App\Models\Device;
use App\Services\Radius\CustomerRadiusSyncService;
use App\Services\Radius\RadiusUserManagementService;
use Illuminate\Support\Facades\Log;

final class RadiusNetworkProvisioner implements NetworkAccessProvisioner
{
    public function __construct(
        private readonly RadiusUserManagementService $radius,
        private readonly CustomerRadiusSyncService $customerSync,
    ) {}

    public function suspendCustomer(Customer $customer, string $reason): void
    {
        if (! $this->radius->isAvailable()) {
            Log::channel('single')->info('network.radius.unavailable', [
                'op' => 'suspend',
                'customer_id' => $customer->id,
                'reason' => $reason,
            ]);

            return;
        }

        $username = $this->radius->usernameForCustomer($customer);
        $this->radius->ensureCustomerUser($customer);
        $this->radius->setReject($username, true);
    }

    public function unsuspendCustomer(Customer $customer): void
    {
        if (! $this->radius->isAvailable()) {
            return;
        }

        $username = $this->radius->usernameForCustomer($customer);
        $this->radius->setReject($username, false);
        $this->radius->clearRateLimit($username);
        $this->customerSync->sync($customer);
    }

    public function syncAccessPolicy(Customer $customer): void
    {
        if (! $this->radius->isAvailable()) {
            return;
        }

        $this->customerSync->sync($customer);

        if (($customer->network_access_state ?? 'active') === 'suspended') {
            $this->radius->setReject($this->radius->usernameForCustomer($customer), true);
        }
    }

    public function pushOnuRuntimeState(Device $onu): void
    {
        // ONU state is not applied via RADIUS in this deployment.
    }
}
