<?php

namespace App\Support;

final class BillingCycleType
{
    public const HOURLY = 'hourly';

    public const DAILY = 'daily';

    public const MONTHLY = 'monthly';

    public const DAYS_30 = 'days_30';

    public const QUARTERLY = 'quarterly';

    public const HALF_YEARLY = 'half_yearly';

    public const YEARLY = 'yearly';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::HOURLY => 'Hourly',
            self::DAILY => 'Daily',
            self::MONTHLY => 'Calendar month',
            self::DAYS_30 => 'Rolling 30 days',
            self::QUARTERLY => 'Quarterly',
            self::HALF_YEARLY => 'Half-yearly',
            self::YEARLY => 'Yearly',
        ];
    }

    public static function label(string $type): string
    {
        return self::options()[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    public static function runsOnSchedule(string $type, string $schedule): bool
    {
        return match ($schedule) {
            'hourly' => $type === self::HOURLY,
            'daily' => in_array($type, [self::DAILY, self::MONTHLY, self::DAYS_30, self::QUARTERLY, self::HALF_YEARLY, self::YEARLY], true),
            default => true,
        };
    }
}
