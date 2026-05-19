<?php

namespace App\Services\Network;

use App\Contracts\NetworkAccessProvisioner;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Subscribers\SubscriberPolicyService;
use App\Support\CustomerStatus;
use Illuminate\Support\Facades\DB;

final class NetworkAccessCoordinator
{
    public function __construct(
        private readonly NetworkAccessProvisioner $provisioner,
    ) {}

    /**
     * True if customer has any invoice with a positive balance and due date in the past.
     */
    public function hasOverdueOpenBalance(Customer $customer): bool
    {
        $graceDays = max(0, (int) config('network.auto_suspend_grace_days', 0));
        $minBalance = max(0.0, (float) config('network.auto_suspend_min_balance', 1));
        $asOf = now()->subDays($graceDays);

        return Invoice::query()
            ->withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->when($customer->tenant_id !== null, fn ($q) => $q->where('tenant_id', $customer->tenant_id))
            ->whereNotIn('status', ['void', 'cancelled', 'paid', 'draft'])
            ->get()
            ->contains(fn (Invoice $invoice): bool => $invoice->balanceDue() >= $minBalance
                && $invoice->due_date !== null
                && $asOf->toDateString() > $invoice->due_date->toDateString());
    }

    public function desiredNetworkAccessState(Customer $customer): string
    {
        $policy = app(SubscriberPolicyService::class);

        if ($policy->shouldApplyServiceExpiry($customer) && $customer->isServiceExpired()
            && ! in_array($customer->status, ['terminated'], true)) {
            return 'suspended';
        }

        if ($policy->isExemptFromAutoNetworkSuspend($customer)) {
            $status = CustomerStatus::normalize((string) $customer->status);
            if (in_array($status, [CustomerStatus::TERMINATED], true)) {
                return 'suspended';
            }

            if ($status === CustomerStatus::SUSPENDED && ($customer->network_access_state ?? '') === 'suspended') {
                return 'suspended';
            }

            return 'active';
        }

        if (! config('network.auto_suspend_enabled', false)) {
            return 'active';
        }

        $status = CustomerStatus::normalize((string) $customer->status);
        if (in_array($status, [CustomerStatus::TERMINATED, CustomerStatus::EXPIRED, CustomerStatus::SUSPENDED], true)) {
            return 'suspended';
        }

        return $this->hasOverdueOpenBalance($customer) ? 'suspended' : 'active';
    }

    public function syncCustomer(Customer $customer): void
    {
        $customer = $this->applyServiceExpiryIfNeeded($customer);

        if (! config('network.auto_suspend_enabled', false)) {
            if (! config('sync.skip_unchanged_network_sync', true)) {
                $this->provisioner->syncAccessPolicy($customer);
            }

            return;
        }

        $desired = $this->desiredNetworkAccessState($customer);
        $current = $customer->network_access_state ?? 'active';

        if ($desired === $current && config('sync.skip_unchanged_network_sync', true)) {
            return;
        }

        DB::transaction(function () use ($customer, $desired, $current): void {
            if ($desired === 'suspended') {
                $this->provisioner->suspendCustomer($customer, 'overdue_invoice');
            } else {
                $this->provisioner->unsuspendCustomer($customer);
                if ($current === 'suspended'
                    && (float) ($customer->reconnection_fee_amount ?? 0) > 0
                    && config('billing.reconnection_fee_enabled', true)) {
                    $customer->forceFill(['pending_reconnection_fee' => true]);
                }
            }

            $customer->forceFill(['network_access_state' => $desired])->saveQuietly();
        });

        $this->provisioner->syncAccessPolicy($customer->fresh() ?? $customer);
    }

    /**
     * When service validity date is past, mark customer inactive and suspend network (idempotent).
     */
    private function applyServiceExpiryIfNeeded(Customer $customer): Customer
    {
        if (! app(SubscriberPolicyService::class)->shouldApplyServiceExpiry($customer)) {
            return $customer;
        }

        if ($customer->service_expires_at === null) {
            return $customer;
        }

        if (! $customer->isServiceExpired()) {
            return $customer;
        }

        if (CustomerStatus::normalize((string) $customer->status) === CustomerStatus::TERMINATED) {
            return $customer;
        }

        $status = CustomerStatus::normalize((string) $customer->status);
        if ($status === CustomerStatus::EXPIRED && ($customer->network_access_state ?? '') === 'suspended') {
            return $customer;
        }

        $customer->forceFill([
            'status' => CustomerStatus::EXPIRED,
            'network_access_state' => 'suspended',
        ])->saveQuietly();

        return $customer->fresh() ?? $customer;
    }
}
