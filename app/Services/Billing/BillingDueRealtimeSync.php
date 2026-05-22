<?php

namespace App\Services\Billing;

use App\Jobs\SyncCustomerNetworkAccessJob;
use App\Models\Customer;
use App\Support\BillingMetricsCache;
use App\Support\CustomerBalanceDue;
use Illuminate\Support\Facades\Cache;

/**
 * Keeps due balances and UI caches in sync immediately after a payment (MikroTik runs in background).
 */
final class BillingDueRealtimeSync
{
    public static function afterPayment(Customer $customer, bool $queueNetwork = true): float
    {
        $customer = $customer->fresh() ?? $customer;

        CustomerBalanceDue::refreshMetaAfterPayment($customer);
        BillingMetricsCache::flush((int) $customer->tenant_id);

        $due = CustomerBalanceDue::amount($customer->fresh() ?? $customer);

        static::flushCaches((int) $customer->tenant_id);

        if ($queueNetwork) {
            SyncCustomerNetworkAccessJob::dispatch((int) $customer->tenant_id, (int) $customer->id)->afterResponse();
        } else {
            SyncCustomerNetworkAccessJob::dispatchSync((int) $customer->tenant_id, (int) $customer->id);
        }

        return $due;
    }

    public static function flushCaches(int $tenantId): void
    {
        BillingMetricsCache::flush($tenantId);
        Cache::forget('dashboard:snapshot:'.$tenantId);

        for ($i = 0; $i < 5; $i++) {
            Cache::forget('clients_dashboard_summary:'.$tenantId.':'.md5('all'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function customerPayload(Customer $customer): array
    {
        $customer = $customer->fresh() ?? $customer;
        $due = CustomerBalanceDue::resolve($customer);

        return [
            'id' => $customer->id,
            'customer_code' => $customer->customer_code,
            'name' => $customer->name,
            'balance_due' => $due['balance_due'],
            'billing_payment_state' => $due['payment_state'],
            'network_access_state' => $customer->network_access_state,
        ];
    }
}
