<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Package;
use App\Support\BillingCycleType;
use Carbon\CarbonInterface;

final class PackagePriceResolver
{
    /**
     * Base recurring price before add-ons (zone > area > promo > list price), scaled to billing cycle.
     */
    public static function resolveCyclePrice(Package $package, ?Customer $customer, ?CarbonInterface $onDate = null): float
    {
        $monthly = self::resolveBaseMonthlyPrice($package, $customer, $onDate);

        return self::scaleToCycle($monthly, $package);
    }

    /**
     * List / zone / promo monthly price (not scaled).
     */
    public static function resolveBaseMonthlyPrice(Package $package, ?Customer $customer, ?CarbonInterface $onDate = null): float
    {
        $onDate ??= now();

        if ($customer?->zone_id) {
            $zp = $package->zonePrices()
                ->where('zone_id', $customer->zone_id)
                ->value('price_monthly');
            if ($zp !== null) {
                return (float) $zp;
            }
        }

        if ($customer?->area_id) {
            $ap = $package->areaPrices()
                ->where('area_id', $customer->area_id)
                ->value('price_monthly');
            if ($ap !== null) {
                return (float) $ap;
            }
        }

        if ($package->promo_starts_at && $package->promo_ends_at
            && $package->promo_price_monthly !== null) {
            $d = $onDate->toDateString();
            if ($d >= $package->promo_starts_at->toDateString()
                && $d <= $package->promo_ends_at->toDateString()) {
                return (float) $package->promo_price_monthly;
            }
        }

        return (float) $package->price_monthly;
    }

    public static function scaleToCycle(float $monthlyAmount, Package $package): float
    {
        $type = $package->billing_cycle_type ?? BillingCycleType::MONTHLY;
        $days = max(1, (int) ($package->billing_cycle_days ?: 30));

        return match ($type) {
            BillingCycleType::HOURLY => round($monthlyAmount / ($days * 24), 2),
            BillingCycleType::DAILY => round($monthlyAmount / $days, 2),
            BillingCycleType::DAYS_30 => round($monthlyAmount, 2),
            default => round($monthlyAmount, 2),
        };
    }
}
