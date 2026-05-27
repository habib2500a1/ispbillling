<?php

namespace App\Services\Mikrotik;

use App\Models\Customer;
use App\Models\MikrotikSessionAlert;
use App\Services\Subscribers\SubscriberPolicyService;
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

        $customer = $alert->customer;
        if ($customer === null) {
            return;
        }

        // Only auto-restore for temporary suspensions triggered by session alerts.
        if (CustomerStatus::normalize((string) $customer->status) !== CustomerStatus::SUSPENDED) {
            return;
        }

        // If any other open alert exists for this subscriber, keep them suspended.
        $stillHasOpenAlerts = MikrotikSessionAlert::query()
            ->where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->id)
            ->whereNull('resolved_at')
            ->exists();

        if ($stillHasOpenAlerts) {
            return;
        }

        // Do not auto re-activate when overdue invoice blocks the line.
        if ($this->networkAccess->hasOverdueOpenBalance($customer)) {
            return;
        }

        // Clear temporary suspension and re-evaluate network state.
        $customer->forceFill([
            'status' => CustomerStatus::ACTIVE,
            'network_access_state' => 'active',
        ])->save();

        $this->networkAccess->syncCustomer($customer->fresh() ?? $customer);
    }
}
