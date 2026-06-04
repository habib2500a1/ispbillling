<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Invoice;
use Carbon\Carbon;

/**
 * Temporary line grace (admin): keeps PPP on past bill/expire date until line_grace_until (current month cap).
 * Permanent per-subscriber grace uses grace_period_days on invoices only.
 */
final class CustomerLineGraceService
{
    public static function defaultGraceDays(): int
    {
        return max(0, (int) config('billing.default_grace_period_days', 0));
    }

    public static function lineGraceUntil(Customer $customer): ?Carbon
    {
        $meta = is_array($customer->meta) ? $customer->meta : [];
        $until = $meta['line_grace_until'] ?? null;
        if ($until === null || $until === '') {
            return null;
        }

        return Carbon::parse($until)->startOfDay();
    }

    public static function hasActiveLineGrace(Customer $customer): bool
    {
        $until = self::lineGraceUntil($customer);

        return $until !== null && now()->startOfDay()->lte($until);
    }

    /**
     * Calendar label: weekday + date in one line (locale-aware).
     */
    public static function formatDisplayDate(Carbon|\Carbon\CarbonInterface $date): string
    {
        return Carbon::parse($date)
            ->locale(app()->getLocale())
            ->translatedFormat('l, j F Y');
    }

    /**
     * Extend line until the chosen date (capped to end of current month).
     */
    public static function extendUntil(Customer $customer, Carbon|\Carbon\CarbonInterface $until): Carbon
    {
        $until = Carbon::parse($until)->startOfDay();
        $today = now()->startOfDay();
        $monthEnd = now()->copy()->endOfMonth()->startOfDay();

        if ($until->lt($today)) {
            throw new \InvalidArgumentException('Grace date cannot be before today.');
        }

        if ($until->gt($monthEnd)) {
            $until = $monthEnd;
        }

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $meta['line_grace_until'] = $until->toDateString();
        $meta['line_grace_extra_days'] = (int) max(1, $today->diffInDays($until));
        $meta['line_grace_extended_at'] = now()->toIso8601String();

        $customer->forceFill(['meta' => $meta])->saveQuietly();

        if (config('network.service_expiry_enforced', true)) {
            app(\App\Services\Network\NetworkAccessCoordinator::class)->syncCustomer($customer->fresh());
        }

        return $until;
    }

    /**
     * Extend line for this billing month only (does not change grace_period_days).
     */
    public static function extendForCurrentMonth(Customer $customer, int $extraDays): Carbon
    {
        $extraDays = max(1, min(31, $extraDays));
        $base = $customer->service_expires_at?->copy()->startOfDay() ?? now()->startOfDay();
        if ($base->lt(now()->startOfDay())) {
            $base = now()->startOfDay();
        }

        return self::extendUntil($customer, $base->copy()->addDays($extraDays));
    }

    public static function suggestedGraceUntil(Customer $customer): Carbon
    {
        $existing = self::lineGraceUntil($customer);
        if ($existing !== null && $existing->gte(now()->startOfDay())) {
            return $existing;
        }

        $base = $customer->service_expires_at?->copy()->startOfDay() ?? now()->startOfDay();
        if ($base->lt(now()->startOfDay())) {
            $base = now()->startOfDay();
        }

        $until = $base->copy()->addDays(3);
        $monthEnd = now()->copy()->endOfMonth()->startOfDay();

        return $until->gt($monthEnd) ? $monthEnd : $until;
    }

    public static function clear(Customer $customer): void
    {
        $meta = is_array($customer->meta) ? $customer->meta : [];
        unset(
            $meta['line_grace_until'],
            $meta['line_grace_extra_days'],
            $meta['line_grace_extended_at'],
        );
        $customer->forceFill(['meta' => $meta])->saveQuietly();
    }

    /** New monthly bill → drop one-off admin grace for the new cycle. */
    public static function clearForNewBillingPeriod(Customer $customer, Invoice $invoice): void
    {
        if (! self::lineGraceUntil($customer)) {
            return;
        }

        self::clear($customer->fresh() ?? $customer);
    }

    public static function label(Customer $customer): ?string
    {
        $until = self::lineGraceUntil($customer);
        if ($until === null) {
            return null;
        }

        if (! self::hasActiveLineGrace($customer)) {
            return 'Line grace ended '.self::formatDisplayDate($until);
        }

        return 'Line grace until '.self::formatDisplayDate($until);
    }
}
