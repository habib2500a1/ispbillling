<?php

namespace App\Services\Olt;

use App\Models\Customer;
use App\Models\Device;
use App\Support\CustomerStatus;

final class OltImpactAnalysisService
{
    /**
     * @return array{
     *     affected_customers: int,
     *     online_customers: int,
     *     offline_customers: int,
     *     monthly_revenue_tk: float,
     *     at_risk_revenue_tk: float,
     *     unauthorized_onus: int
     * }
     */
    public function forOlt(Device $olt): array
    {
        $onus = Device::query()
            ->where('olt_id', $olt->id)
            ->where('type', 'onu')
            ->get(['id', 'customer_id', 'onu_oper_status']);

        $customerIds = $onus->pluck('customer_id')->filter()->unique()->values();
        $customers = Customer::query()
            ->whereIn('id', $customerIds)
            ->where('status', '!=', CustomerStatus::TERMINATED)
            ->with('package:id,price_monthly')
            ->get(['id', 'package_id', 'is_ppp_online', 'status']);

        $monthlyRevenue = (float) $customers->sum(fn (Customer $c) => (float) ($c->package?->price_monthly ?? 0));
        $offlineIds = $onus
            ->filter(fn (Device $o) => ! in_array(strtolower((string) $o->onu_oper_status), ['online', 'active', 'up'], true))
            ->pluck('customer_id')
            ->filter()
            ->unique();

        $atRisk = (float) $customers
            ->whereIn('id', $offlineIds)
            ->sum(fn (Customer $c) => (float) ($c->package?->price_monthly ?? 0));

        $unauthorized = $onus->filter(fn (Device $o) => in_array(
            strtolower((string) $o->onu_oper_status),
            ['unauthorized', 'auth_fail', 'illegal'],
            true,
        ))->count();

        return [
            'affected_customers' => $customerIds->count(),
            'online_customers' => $customers->where('is_ppp_online', true)->count(),
            'offline_customers' => $customers->where('is_ppp_online', false)->count(),
            'monthly_revenue_tk' => round($monthlyRevenue, 2),
            'at_risk_revenue_tk' => round($atRisk, 2),
            'unauthorized_onus' => $unauthorized,
        ];
    }
}
