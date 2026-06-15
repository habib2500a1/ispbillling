<?php

namespace App\Support;

use App\Jobs\KickPppSessionJob;
use App\Jobs\PushCustomerPackageChangeJob;
use App\Jobs\SyncCustomerNetworkAccessJob;
use App\Models\Customer;

/**
 * Queue MikroTik/RADIUS work so web requests never block on router API latency.
 */
final class CustomerNetworkSyncDispatcher
{
    public static function dispatch(int $tenantId, int $customerId): void
    {
        if (config('queue_ops.heavy_jobs_enabled', false)) {
            SyncCustomerNetworkAccessJob::dispatch($tenantId, $customerId);

            return;
        }

        SyncCustomerNetworkAccessJob::dispatch($tenantId, $customerId)->afterResponse();
    }

    public static function dispatchSync(int $tenantId, int $customerId): void
    {
        SyncCustomerNetworkAccessJob::dispatchSync($tenantId, $customerId);
    }

    public static function kickSessions(Customer $customer): void
    {
        $tenantId = (int) $customer->tenant_id;
        $customerId = (int) $customer->id;

        if (config('queue_ops.heavy_jobs_enabled', false)) {
            KickPppSessionJob::dispatch($tenantId, $customerId);

            return;
        }

        KickPppSessionJob::dispatchSync($tenantId, $customerId);
    }

    public static function packageChange(Customer $customer): void
    {
        $tenantId = (int) $customer->tenant_id;
        $customerId = (int) $customer->id;

        if (config('queue_ops.heavy_jobs_enabled', false)) {
            PushCustomerPackageChangeJob::dispatch($tenantId, $customerId);

            return;
        }

        PushCustomerPackageChangeJob::dispatch($tenantId, $customerId)->afterResponse();
    }
}
