<?php

namespace App\Services\Resellers;

use App\Models\Customer;
use App\Models\Reseller;
use App\Support\ResellerCustomerBillingPolicy;
use App\Support\ResellerNewCustomerChargeMode;
use Carbon\Carbon;

/**
 * Reseller-managed customer billing: proration, first-month rules, prepaid vs postpaid suspend.
 */
final class ResellerCustomerBillingEngine
{
    public function resellerFor(Customer $customer): ?Reseller
    {
        if ($customer->reseller_id === null) {
            return null;
        }

        return $customer->relationLoaded('reseller')
            ? $customer->reseller
            : Reseller::query()->find($customer->reseller_id);
    }

    public function chargeModeFor(Reseller $reseller, ?Customer $customer = null): string
    {
        if ($customer !== null) {
            $meta = is_array($customer->meta) ? $customer->meta : [];
            $override = $meta['new_customer_charge_mode'] ?? null;
            if (filled($override) && ($reseller->reseller_can_override_charge_mode || config('reseller_billing.reseller_can_override_charge_mode', false))) {
                return (string) $override;
            }
        }

        return (string) ($reseller->new_customer_charge_mode
            ?? config('reseller_billing.default_new_customer_charge_mode', ResellerNewCustomerChargeMode::PRORATED));
    }

    /**
     * Skip calendar proration (full cycle price before first-month multiplier).
     */
    public function shouldSkipProration(Customer $customer, ?Reseller $reseller = null): bool
    {
        $reseller ??= $this->resellerFor($customer);
        if ($reseller === null) {
            return false;
        }

        return $this->chargeModeFor($reseller, $customer) === ResellerNewCustomerChargeMode::FULL_MONTH;
    }

    /**
     * Multiplier on package line for first invoice in join month (0, 0.5, 1).
     */
    public function firstMonthPackageMultiplier(Customer $customer, ?Reseller $reseller = null, ?Carbon $referenceDate = null): float
    {
        $reseller ??= $this->resellerFor($customer);
        if ($reseller === null) {
            return 1.0;
        }

        if (! $this->isFirstMonthOfService($customer, $referenceDate)) {
            return 1.0;
        }

        return match ($this->chargeModeFor($reseller, $customer)) {
            ResellerNewCustomerChargeMode::FIRST_MONTH_FREE => 0.0,
            ResellerNewCustomerChargeMode::FIRST_MONTH_HALF => 0.5,
            default => 1.0,
        };
    }

    public function isFirstMonthOfService(Customer $customer, ?Carbon $referenceDate = null): bool
    {
        if ($customer->joined_at === null) {
            return false;
        }

        $ref = ($referenceDate ?? now())->copy()->startOfDay();
        $joined = Carbon::parse($customer->joined_at)->startOfDay();

        return $joined->isSameMonth($ref) && $joined->year === $ref->year;
    }

    /**
     * Apply reseller defaults when creating a subscriber.
     */
    public function applyDefaultsToNewCustomer(Customer $customer, Reseller $reseller): void
    {
        if (blank($customer->billing_mode)) {
            $mode = (string) ($reseller->default_customer_billing_mode ?? 'prepaid');
            $customer->billing_mode = in_array($mode, ['prepaid', 'postpaid'], true) ? $mode : 'prepaid';
        }

        if ($customer->grace_period_days === null) {
            $grace = ($customer->billing_mode ?? 'postpaid') === 'postpaid'
                ? (int) ($reseller->default_postpaid_grace_days ?? config('reseller_billing.default_postpaid_grace_days', 10))
                : (int) ($reseller->default_prepaid_grace_days ?? config('reseller_billing.default_prepaid_grace_days', 5));
            $customer->grace_period_days = max(0, $grace);
        }

        if ((int) ($customer->billing_day ?? 0) < 1) {
            $customer->billing_day = (int) config('billing.default_billing_day', 1);
        }
    }

    /**
     * Prepaid: suspend after grace when due (unless customer override).
     * Postpaid: keep active when reseller allows overdue (reseller_controlled).
     */
    public function isExemptFromAutoSuspend(Customer $customer): bool
    {
        $reseller = $this->resellerFor($customer);
        if ($reseller === null) {
            return false;
        }

        $policy = $reseller->customer_billing_policy ?? ResellerCustomerBillingPolicy::RESELLER_CONTROLLED;
        $billingMode = (string) ($customer->billing_mode ?? 'postpaid');

        if ($policy === ResellerCustomerBillingPolicy::NEVER_AUTO) {
            return true;
        }

        $meta = is_array($customer->meta) ? $customer->meta : [];
        if (array_key_exists('allow_active_when_due', $meta) && filter_var($meta['allow_active_when_due'], FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        if ($policy === ResellerCustomerBillingPolicy::FOLLOW_ISP_DUE) {
            return false;
        }

        // Reseller controlled: postpaid may stay online with due; prepaid always follows due/grace.
        if (in_array($billingMode, ['prepaid', 'advance'], true)) {
            return false;
        }

        return (bool) $reseller->allow_overdue_customers_active;
    }

    public function defaultGraceDaysFor(Customer $customer, ?Reseller $reseller = null): int
    {
        $reseller ??= $this->resellerFor($customer);
        if ($customer->grace_period_days !== null && (int) $customer->grace_period_days > 0) {
            return (int) $customer->grace_period_days;
        }

        if ($reseller === null) {
            return max(0, (int) config('billing.default_grace_period_days', 0));
        }

        $mode = (string) ($customer->billing_mode ?? 'postpaid');

        return $mode === 'postpaid'
            ? max(0, (int) ($reseller->default_postpaid_grace_days ?? 10))
            : max(0, (int) ($reseller->default_prepaid_grace_days ?? 5));
    }
}
