<?php

namespace App\Services\Billing;

use App\Models\Package;
use App\Support\BillingCycleType;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final class BillingPeriodResolver
{
    /**
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon} [period_start, period_end] inclusive dates
     */
    public static function resolve(Package $package, CarbonInterface $reference): array
    {
        $ref = Carbon::parse($reference)->startOfDay();
        $type = $package->billing_cycle_type ?? BillingCycleType::MONTHLY;
        $cycleDays = max(1, (int) ($package->billing_cycle_days ?: 30));

        return match ($type) {
            BillingCycleType::HOURLY => [
                $ref->copy()->startOfHour(),
                $ref->copy()->endOfHour(),
            ],
            BillingCycleType::DAILY => [
                $ref->copy()->startOfDay(),
                $ref->copy()->endOfDay(),
            ],
            BillingCycleType::DAYS_30 => [
                $ref->copy()->subDays($cycleDays - 1)->startOfDay(),
                $ref->copy()->endOfDay(),
            ],
            BillingCycleType::QUARTERLY => [
                $ref->copy()->firstOfQuarter()->startOfDay(),
                $ref->copy()->lastOfQuarter()->endOfDay(),
            ],
            BillingCycleType::HALF_YEARLY => self::halfYearWindow($ref),
            BillingCycleType::YEARLY => [
                $ref->copy()->startOfYear()->startOfDay(),
                $ref->copy()->endOfYear()->endOfDay(),
            ],
            default => [
                $ref->copy()->startOfMonth()->startOfDay(),
                $ref->copy()->endOfMonth()->endOfDay(),
            ],
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function halfYearWindow(Carbon $ref): array
    {
        if ((int) $ref->month <= 6) {
            return [
                $ref->copy()->month(1)->day(1)->startOfDay(),
                $ref->copy()->month(6)->endOfMonth()->endOfDay(),
            ];
        }

        return [
            $ref->copy()->month(7)->day(1)->startOfDay(),
            $ref->copy()->month(12)->endOfMonth()->endOfDay(),
        ];
    }
}
