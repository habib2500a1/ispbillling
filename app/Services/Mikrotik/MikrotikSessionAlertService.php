<?php

namespace App\Services\Mikrotik;

use App\Models\Customer;
use App\Models\MikrotikSessionAlert;
use App\Services\Network\NetworkAccessCoordinator;
use App\Support\CustomerStatus;

final class MikrotikSessionAlertService
{
    public function __construct(
        private readonly NetworkAccessCoordinator $networkAccess,
    ) {}

    public function suspendFromAlert(MikrotikSessionAlert $alert): void
    {
        $customer = $alert->customer;
        if ($customer === null) {
            return;
        }

        $this->suspendCustomer($customer);
        $this->resolve($alert);
    }

    public function suspendCustomer(Customer $customer): void
    {
        $customer->forceFill([
            'status' => CustomerStatus::SUSPENDED,
            'network_access_state' => 'suspended',
        ])->save();

        $this->networkAccess->syncCustomer($customer->fresh() ?? $customer);
    }

    public function resolve(MikrotikSessionAlert $alert): void
    {
        if ($alert->resolved_at === null) {
            $alert->forceFill(['resolved_at' => now()])->save();
        }
    }
}
