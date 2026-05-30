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

        // Service date past: suspend line only when there is overdue invoice due (not expiry alone).
        if ($policy->shouldApplyServiceExpiry($customer) && $customer->isServiceExpired()
            && ! in_array($customer->status, ['terminated'], true)
            && $this->hasOverdueOpenBalance($customer)) {
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

        if ($status === CustomerStatus::TERMINATED) {
            return 'suspended';
        }

        // Validity extended: do not keep line off only because status is still "expired".
        if ($status === CustomerStatus::EXPIRED
            && ! $customer->isServiceExpired()
            && ! $this->hasOverdueOpenBalance($customer)) {
            return 'active';
        }

        if ($status === CustomerStatus::SUSPENDED && ($customer->network_access_state ?? '') === 'suspended') {
            return 'suspended';
        }

        return $this->hasOverdueOpenBalance($customer) ? 'suspended' : 'active';
    }

    public function syncCustomer(Customer $customer): void
    {
        $customer = $this->restoreServiceValidityIfNeeded($customer);
        $customer = $this->applyServiceExpiryIfNeeded($customer);

        if (! config('network.auto_suspend_enabled', false)) {
            // Auto-suspend off: still push MikroTik/RADIUS so renew / Net ON re-enables secrets.
            $this->provisioner->syncAccessPolicy($customer);

            return;
        }

        $desired = $this->desiredNetworkAccessState($customer);
        $current = $customer->network_access_state ?? 'active';

        if ($desired === $current && config('sync.skip_unchanged_network_sync', true)) {
            // Even if DB state already matches the desired state, ensure the
            // provisioning backend (MikroTik/RADIUS) is aligned.
            // Otherwise "manual renew/network_on" can leave PPP secret disabled.
            $this->provisioner->syncAccessPolicy($customer);
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
     * When validity was extended into the future, clear stale expired state (idempotent).
     */
    private function restoreServiceValidityIfNeeded(Customer $customer): Customer
    {
        if (! app(SubscriberPolicyService::class)->shouldApplyServiceExpiry($customer)) {
            return $customer;
        }

        if ($customer->service_expires_at === null || $customer->isServiceExpired()) {
            return $customer;
        }

        $status = CustomerStatus::normalize((string) $customer->status);
        if ($status !== CustomerStatus::EXPIRED && ($customer->network_access_state ?? 'active') !== 'suspended') {
            return $customer;
        }

        if ($this->hasOverdueOpenBalance($customer)) {
            return $customer;
        }

        $customer->forceFill([
            'status' => CustomerStatus::ACTIVE,
            'network_access_state' => 'active',
        ])->saveQuietly();

        return $customer->fresh() ?? $customer;
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

        $updates = ['status' => CustomerStatus::EXPIRED];
        if ($this->hasOverdueOpenBalance($customer)) {
            $updates['network_access_state'] = 'suspended';
        }

        $customer->forceFill($updates)->saveQuietly();

        return $customer->fresh() ?? $customer;
    }

    public function canAdminForceNetOn(Customer $customer): bool
    {
        return ! $this->hasOverdueOpenBalance($customer);
    }
}
