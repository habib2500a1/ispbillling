<?php

namespace App\Services\Subscribers;

use App\Models\Customer;
use App\Support\CustomerStatus;
use App\Support\SubscriberType;

final class SubscriberPolicyService
{
    public function shouldGenerateInvoice(Customer $customer): bool
    {
        if (SubscriberType::skipsBilling((string) ($customer->subscriber_type ?? SubscriberType::STANDARD))) {
            return false;
        }

        if (CustomerStatus::normalize((string) $customer->status) !== CustomerStatus::ACTIVE) {
            return false;
        }

        if ($customer->package_id === null) {
            return false;
        }

        return true;
    }

    public function isExemptFromAutoNetworkSuspend(Customer $customer): bool
    {
        $override = $customer->getAttributes()['auto_suspend_override'] ?? null;
        if ($override !== null) {
            return ! filter_var($override, FILTER_VALIDATE_BOOLEAN);
        }

        return SubscriberType::isExemptFromAutoSuspend((string) ($customer->subscriber_type ?? SubscriberType::STANDARD));
    }

    public function shouldApplyServiceExpiry(Customer $customer): bool
    {
        if ($this->isExemptFromAutoNetworkSuspend($customer)) {
            return false;
        }

        return (bool) config('network.service_expiry_enforced', true);
    }
}
