<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Package;
use App\Support\BillingCycleType;
use App\Support\CustomerBalanceDue;
use App\Support\CustomerStatus;
use App\Support\PaymentRenewalPolicy;
use Carbon\CarbonInterface;

final class ServiceExpiryExtensionService
{
    /**
     * After a bill is fully paid, renew validity and re-enable the line when policy allows.
     */
    public function activateAfterFullPayment(Customer $customer, ?\App\Models\Payment $payment = null): bool
    {
        $customer = $customer->fresh() ?? $customer;

        if (! $this->shouldAutoActivateLine($customer)) {
            return false;
        }

        if (CustomerBalanceDue::amount($customer) > 0.01) {
            return false;
        }

        $status = CustomerStatus::normalize((string) $customer->status);
        if ($status === CustomerStatus::TERMINATED) {
            return false;
        }

        if ($status === CustomerStatus::SUSPENDED) {
            return false;
        }

        $changed = false;

        if ($customer->isServiceExpired() || $status === CustomerStatus::EXPIRED) {
            $this->extendForPaidCycle(
                $customer,
                is_array($payment?->meta) ? ($payment->meta['renewal_policy'] ?? null) : null,
                $payment?->paid_at,
            );
            $changed = true;
            $customer = $customer->fresh() ?? $customer;
        }

        if (($customer->network_access_state ?? '') === 'suspended') {
            $customer->forceFill([
                'status' => CustomerStatus::ACTIVE,
                'network_access_state' => 'active',
            ])->saveQuietly();
            $changed = true;
        } elseif ($status !== CustomerStatus::ACTIVE && ! $customer->isServiceExpired()) {
            $customer->forceFill(['status' => CustomerStatus::ACTIVE])->saveQuietly();
            $changed = true;
        }

        return $changed;
    }

    /**
     * Extend service_expires_at by one billing cycle from the later of now or current expiry.
     */
    public function extendForPaidCycle(
        Customer $customer,
        ?string $renewalPolicyOverride = null,
        CarbonInterface|string|null $paidAt = null,
    ): void {
        $this->extendForPrepaidMonths($customer, 1, null, $renewalPolicyOverride, $paidAt);
    }

    /**
     * Extend service validity by multiple billing cycles (advance payment).
     */
    public function extendForPrepaidMonths(
        Customer $customer,
        int $months,
        ?\App\Models\Payment $payment = null,
        ?string $renewalPolicyOverride = null,
        CarbonInterface|string|null $paidAt = null,
    ): void {
        $months = max(1, $months);
        $package = $customer->package;
        if (! $package instanceof Package) {
            return;
        }

        $days = $this->cycleDays($package) * $months;
        if ($days <= 0) {
            return;
        }

        $override = $renewalPolicyOverride;
        if ($override === null && is_array($payment?->meta)) {
            $override = $payment->meta['renewal_policy'] ?? null;
        }

        $base = PaymentRenewalPolicy::resolveBaseDate($customer, $override, $paidAt ?? $payment?->paid_at);

        $updates = [
            'service_expires_at' => $base->copy()->addDays($days)->toDateString(),
            'status' => CustomerStatus::ACTIVE,
        ];

        if ($this->shouldAutoActivateLine($customer)) {
            $updates['network_access_state'] = 'active';
        }

        $customer->forceFill($updates)->saveQuietly();
    }

    /**
     * Turn the line back on after dues are cleared without changing expiry again.
     */
    public function activateLineOnly(Customer $customer): bool
    {
        $customer = $customer->fresh() ?? $customer;

        if (! $this->shouldAutoActivateLine($customer)) {
            return false;
        }

        if (CustomerBalanceDue::amount($customer) > 0.01) {
            return false;
        }

        $status = CustomerStatus::normalize((string) $customer->status);
        if ($status === CustomerStatus::TERMINATED || $status === CustomerStatus::SUSPENDED) {
            return false;
        }

        $changed = false;

        if (($customer->network_access_state ?? '') === 'suspended') {
            $customer->forceFill([
                'status' => CustomerStatus::ACTIVE,
                'network_access_state' => 'active',
            ])->saveQuietly();
            $changed = true;
            $customer = $customer->fresh() ?? $customer;
        } elseif ($status !== CustomerStatus::ACTIVE && ! $customer->isServiceExpired()) {
            $customer->forceFill(['status' => CustomerStatus::ACTIVE])->saveQuietly();
            $changed = true;
        }

        return $changed;
    }

    public function shouldAutoActivateLine(Customer $customer): bool
    {
        $meta = is_array($customer->meta) ? $customer->meta : [];
        if (array_key_exists('auto_activate', $meta)) {
            return filter_var($meta['auto_activate'], FILTER_VALIDATE_BOOLEAN);
        }

        return true;
    }

    public function cycleDays(Package $package): int
    {
        $type = $package->billing_cycle_type ?? BillingCycleType::MONTHLY;
        $cycleDays = max(1, (int) ($package->billing_cycle_days ?: 30));

        return match ($type) {
            BillingCycleType::HOURLY => 1,
            BillingCycleType::DAILY => 1,
            BillingCycleType::DAYS_30 => $cycleDays,
            BillingCycleType::QUARTERLY => 90,
            BillingCycleType::HALF_YEARLY => 182,
            BillingCycleType::YEARLY => 365,
            default => (int) now()->daysInMonth,
        };
    }
}
