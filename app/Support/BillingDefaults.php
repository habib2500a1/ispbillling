<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class BillingDefaults
{
    /**
     * Day of month (1–28) when isp:generate-bills creates monthly invoices for a subscriber.
     */
    public static function billingDay(?int $override = null): int
    {
        if ($override !== null && $override >= 1 && $override <= 28) {
            return $override;
        }

        $configured = (int) config('billing.default_billing_day', 1);

        return max(1, min(28, $configured > 0 ? $configured : 1));
    }

    /**
     * Bill day from activation / join date (day they opened), capped 1–28.
     */
    public static function billingDayForActivation(null|string|CarbonInterface $joinedAt = null): int
    {
        $date = $joinedAt !== null && $joinedAt !== ''
            ? Carbon::parse($joinedAt)
            : now();

        return max(1, min(28, (int) $date->day));
    }

    /**
     * Default expire day for new subscribers (last day of current month, capped at 31).
     */
    public static function defaultExpireDay(): int
    {
        $configured = (int) config('billing.default_expire_day', 0);

        if ($configured >= 1 && $configured <= 31) {
            return $configured;
        }

        return (int) now()->endOfMonth()->format('j');
    }

    /**
     * Extract calendar day (1–31) from a stored expire date.
     */
    public static function expireDayFromDate(null|string|CarbonInterface $date): int
    {
        if ($date === null || $date === '') {
            return self::defaultExpireDay();
        }

        return (int) Carbon::parse($date)->day;
    }

    /**
     * Next service_expires_at date from expire day number only.
     */
    public static function dateFromExpireDay(int $day, ?CarbonInterface $reference = null): string
    {
        $ref = Carbon::parse($reference ?? now())->startOfDay();
        $day = max(1, min(31, $day));
        $safeDay = min($day, $ref->daysInMonth);
        $candidate = $ref->copy()->day($safeDay);

        if ($candidate->lt($ref)) {
            $next = $ref->copy()->addMonth();
            $safeDay = min($day, $next->daysInMonth);
            $candidate = $next->day($safeDay);
        }

        return $candidate->toDateString();
    }

    public static function expireDayLabel(null|string|CarbonInterface $date): string
    {
        if ($date === null || $date === '') {
            return '—';
        }

        return (string) self::expireDayFromDate($date);
    }
}
