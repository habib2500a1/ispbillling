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

        $meta = is_array($customer->meta) ? $customer->meta : [];
        if (array_key_exists('auto_invoice', $meta) && ! filter_var($meta['auto_invoice'], FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $mode = (string) ($customer->billing_mode ?? 'postpaid');
        if (in_array($mode, ['prepaid', 'advance'], true) && ! config('billing.prepaid_auto_invoice', true)) {
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
