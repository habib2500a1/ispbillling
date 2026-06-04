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
            if ($zp !== null && (float) $zp > 0) {
                return self::applyCustomerMonthlyDiscount((float) $zp, $customer);
            }
        }

        if ($customer?->area_id) {
            $ap = $package->areaPrices()
                ->where('area_id', $customer->area_id)
                ->value('price_monthly');
            if ($ap !== null && (float) $ap > 0) {
                return self::applyCustomerMonthlyDiscount((float) $ap, $customer);
            }
        }

        if ($package->promo_starts_at && $package->promo_ends_at
            && $package->promo_price_monthly !== null) {
            $d = $onDate->toDateString();
            if ($d >= $package->promo_starts_at->toDateString()
                && $d <= $package->promo_ends_at->toDateString()) {
                return self::applyCustomerMonthlyDiscount((float) $package->promo_price_monthly, $customer);
            }
        }

        return self::applyCustomerMonthlyDiscount((float) $package->price_monthly, $customer);
    }

    private static function applyCustomerMonthlyDiscount(float $monthly, ?Customer $customer): float
    {
        $discount = (float) data_get($customer?->meta, 'monthly_discount_bdt', 0);

        return max(0, round($monthly - $discount, 2));
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
