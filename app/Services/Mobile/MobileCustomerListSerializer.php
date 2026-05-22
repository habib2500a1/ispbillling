<?php

namespace App\Services\Mobile;

use App\Models\Customer;
use App\Support\BillingDefaults;
use App\Support\CustomerBalanceDue;

final class MobileCustomerListSerializer
{
    /**
     * @return array<string, mixed>
     */
    public static function row(Customer $customer, bool $withDue = true): array
    {
        $customer->loadMissing(['package:id,name,download_mbps,price_monthly', 'zone:id,name', 'subzone:id,name', 'area:id,name']);

        $due = $withDue ? CustomerBalanceDue::resolve($customer) : null;
        $zone = collect([$customer->zone?->name, $customer->subzone?->name])
            ->filter()
            ->implode(' · ');

        return [
            'id' => $customer->id,
            'customer_code' => $customer->customer_code,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'username' => $customer->pppLoginName(),
            'address' => $customer->address,
            'zone' => $zone !== '' ? $zone : ($customer->area?->name),
            'status' => $customer->status,
            'package' => $customer->package?->name,
            'package_speed' => $customer->package?->download_mbps,
            'monthly_bill' => $customer->package?->price_monthly !== null
                ? round((float) $customer->package->price_monthly, 2)
                : null,
            'billing_mode' => $customer->billing_mode,
            'network_access_state' => $customer->network_access_state ?? 'active',
            'network_on' => ($customer->network_access_state ?? 'active') !== 'suspended',
            'service_expires_at' => $customer->service_expires_at?->toDateString(),
            'expire_day' => BillingDefaults::expireDayFromDate($customer->service_expires_at?->toDateString()),
            'is_online' => $customer->isPppOnline(),
            'balance_due' => $withDue ? ($due['balance_due'] ?? 0) : CustomerBalanceDue::amount($customer),
            'payment_state' => $withDue ? ($due['payment_state'] ?? null) : null,
        ];
    }
}
