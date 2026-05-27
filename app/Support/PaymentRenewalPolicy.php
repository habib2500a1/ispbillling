<?php

namespace App\Support;

use App\Models\Customer;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class PaymentRenewalPolicy
{
    public const DEFAULT = 'default';

    public const SMART = 'smart';

    public const FROM_PAYMENT_DATE = 'from_payment_date';

    public const FROM_PREVIOUS_EXPIRY = 'from_previous_expiry';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::DEFAULT => 'System default',
            self::SMART => 'Smart — grace period, then payment date',
            self::FROM_PAYMENT_DATE => 'Payment date (আজ থেকে renew)',
            self::FROM_PREVIOUS_EXPIRY => 'Previous expire date (আগের তারিখ)',
        ];
    }

    public static function systemDefault(): string
    {
        return self::normalize((string) config('billing.payment_renewal_base', self::SMART));
    }

    public static function lateGraceDays(): int
    {
        return max(0, (int) config('billing.payment_renewal_late_grace_days', 7));
    }

    public static function forCustomer(Customer $customer, ?string $override = null): string
    {
        if ($override !== null && $override !== '' && $override !== self::DEFAULT) {
            return self::normalize($override);
        }

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $customerPolicy = (string) ($meta['payment_renewal_base'] ?? '');
        if ($customerPolicy !== '' && $customerPolicy !== self::DEFAULT) {
            return self::normalize($customerPolicy);
        }

        return self::normalize(self::systemDefault());
    }

    public static function resolveBaseDate(
        Customer $customer,
        ?string $policyOverride = null,
        CarbonInterface|string|null $paidAt = null,
    ): Carbon {
        $paidAt = Carbon::parse($paidAt ?? now())->startOfDay();
        $policy = self::forCustomer($customer, $policyOverride);
        $previousExpiry = $customer->service_expires_at?->copy()->startOfDay();

        return match ($policy) {
            self::FROM_PAYMENT_DATE => $paidAt->copy(),
            self::FROM_PREVIOUS_EXPIRY => $previousExpiry?->copy() ?? $paidAt->copy(),
            default => self::resolveSmartBase($previousExpiry, $paidAt),
        };
    }

    public static function describeForCustomer(Customer $customer, ?string $policyOverride = null): string
    {
        $policy = self::forCustomer($customer, $policyOverride);

        return match ($policy) {
            self::FROM_PAYMENT_DATE => 'Renew from payment date',
            self::FROM_PREVIOUS_EXPIRY => 'Renew from previous expire date',
            default => sprintf(
                'Smart — within %d day(s) after expire use previous date, otherwise payment date',
                self::lateGraceDays(),
            ),
        };
    }

    private static function resolveSmartBase(?Carbon $previousExpiry, Carbon $paidAt): Carbon
    {
        if ($previousExpiry === null) {
            return $paidAt->copy();
        }

        if ($previousExpiry->greaterThanOrEqualTo($paidAt)) {
            return $previousExpiry->copy();
        }

        $daysLate = (int) $previousExpiry->diffInDays($paidAt, false);
        if ($daysLate >= 0 && $daysLate <= self::lateGraceDays()) {
            return $previousExpiry->copy();
        }

        return $paidAt->copy();
    }

    public static function normalize(string $policy): string
    {
        $policy = strtolower(trim($policy));

        return match ($policy) {
            self::FROM_PAYMENT_DATE, self::FROM_PREVIOUS_EXPIRY, self::SMART => $policy,
            default => self::SMART,
        };
    }
}
