<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Payment;
use App\Support\BillingCycleType;
use App\Support\CustomerNetworkSync;
use App\Support\CustomerStatus;
use App\Support\PaymentRenewalPolicy;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final class ServiceExpiryExtensionService
{
    /**
     * Extend service_expires_at after a paid cycle using renewal policy from payment meta or tenant defaults.
     */
    public function extendForPaidCycle(Customer $customer, ?Payment $payment = null): void
    {
        $customer = $customer->fresh() ?? $customer;
        $package = $customer->package;
        if (! $package instanceof Package) {
            return;
        }

        $meta = $payment !== null && is_array($payment->meta) ? $payment->meta : [];
        $policyOverride = $this->policyFromPaymentMeta($meta);
        $paidAt = $payment?->paid_at ?? now();
        $cycles = max(1, (int) ($meta['prepay_months'] ?? 1));

        $base = PaymentRenewalPolicy::resolveBaseDate($customer, $policyOverride, $paidAt);
        $expiresAt = $this->extendFromBase($base, $package, $cycles);

        $customer->forceFill([
            'service_expires_at' => $expiresAt->toDateString(),
            'status' => CustomerStatus::ACTIVE,
            'network_access_state' => 'active',
        ])->saveQuietly();

        if ($payment !== null) {
            $this->maybeUpdateBillingDay($customer, $payment);
        }

        CustomerNetworkSync::runNow($customer->fresh() ?? $customer);
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

    private function extendFromBase(CarbonInterface $base, Package $package, int $cycles): Carbon
    {
        $cursor = Carbon::parse($base)->startOfDay();

        for ($i = 0; $i < $cycles; $i++) {
            $cursor = $this->addOneCycle($cursor, $package);
        }

        return $cursor;
    }

    private function addOneCycle(Carbon $from, Package $package): Carbon
    {
        $type = $package->billing_cycle_type ?? BillingCycleType::MONTHLY;

        return match ($type) {
            BillingCycleType::HOURLY => $from->copy()->addHour(),
            BillingCycleType::DAILY => $from->copy()->addDay(),
            BillingCycleType::DAYS_30 => $from->copy()->addDays(max(1, (int) ($package->billing_cycle_days ?: 30))),
            BillingCycleType::QUARTERLY => $from->copy()->addMonths(3),
            BillingCycleType::HALF_YEARLY => $from->copy()->addMonths(6),
            BillingCycleType::YEARLY => $from->copy()->addYear(),
            default => $from->copy()->addMonthNoOverflow(),
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function policyFromPaymentMeta(array $meta): ?string
    {
        $policy = (string) ($meta['renewal_policy'] ?? '');
        if ($policy === '' || $policy === PaymentRenewalPolicy::DEFAULT) {
            return null;
        }

        return PaymentRenewalPolicy::normalize($policy);
    }

    private function maybeUpdateBillingDay(Customer $customer, Payment $payment): void
    {
        $meta = is_array($payment->meta) ? $payment->meta : [];
        if (! empty($meta['skip_billing_date_update'])) {
            return;
        }

        $paidAt = $payment->paid_at ?? now();
        $day = min(28, max(1, (int) $paidAt->day));
        if ((int) $customer->billing_day === $day) {
            return;
        }

        $customer->forceFill(['billing_day' => $day])->saveQuietly();
    }
}
