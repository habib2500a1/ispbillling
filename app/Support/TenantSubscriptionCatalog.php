<?php

namespace App\Support;

/**
 * SaaS sell packages — per-tenant customer cap, platform monthly fee, bill day.
 */
final class TenantSubscriptionCatalog
{
    public const PLAN_STARTER_100 = 'starter_100';

    public const PLAN_GROWTH_200 = 'growth_200';

    public const PLAN_BUSINESS_500 = 'business_500';

    public const PLAN_ENTERPRISE = 'enterprise';

    public const PLAN_CUSTOM = 'custom';

    /**
     * @return array<string, array{label: string, max_customers: ?int, monthly_fee_bdt: float, description: string}>
     */
    public static function plans(): array
    {
        return [
            self::PLAN_STARTER_100 => [
                'label' => 'Starter — 100 customers',
                'max_customers' => 100,
                'monthly_fee_bdt' => 500.0,
                'description' => 'Small ISP / new partner',
            ],
            self::PLAN_GROWTH_200 => [
                'label' => 'Growth — 200 customers',
                'max_customers' => 200,
                'monthly_fee_bdt' => 800.0,
                'description' => 'Growing local ISP',
            ],
            self::PLAN_BUSINESS_500 => [
                'label' => 'Business — 500 customers',
                'max_customers' => 500,
                'monthly_fee_bdt' => 1500.0,
                'description' => 'Multi-branch ISP',
            ],
            self::PLAN_ENTERPRISE => [
                'label' => 'Enterprise — unlimited',
                'max_customers' => null,
                'monthly_fee_bdt' => 2500.0,
                'description' => 'Franchise / large operator',
            ],
            self::PLAN_CUSTOM => [
                'label' => 'Custom plan',
                'max_customers' => null,
                'monthly_fee_bdt' => 0.0,
                'description' => 'Set your own cap and price',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function selectOptions(): array
    {
        $options = [];
        foreach (self::plans() as $key => $plan) {
            $fee = number_format($plan['monthly_fee_bdt'], 0);
            $cap = $plan['max_customers'] === null ? '∞' : (string) $plan['max_customers'];
            $options[$key] = "{$plan['label']} · {$cap} subs · {$fee} BDT/mo";
        }

        return $options;
    }

    /**
     * @return array{plan_key: string, plan_name: string, max_customers: ?int, monthly_fee_bdt: float, billing_day: int, status: string}
     */
    public static function defaultsForPlan(string $planKey, int $billingDay = 1): array
    {
        $plan = self::plans()[$planKey] ?? self::plans()[self::PLAN_STARTER_100];

        return [
            'plan_key' => $planKey,
            'plan_name' => $plan['label'],
            'max_customers' => $plan['max_customers'],
            'monthly_fee_bdt' => $plan['monthly_fee_bdt'],
            'billing_day' => max(1, min(28, $billingDay)),
            'status' => 'active',
        ];
    }
}
