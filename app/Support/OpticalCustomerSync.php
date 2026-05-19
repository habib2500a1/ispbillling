<?php

namespace App\Support;

use App\Jobs\SyncCustomerOnuFromOltJob;
use App\Models\Customer;

/**
 * Dispatch per-subscriber OLT sync. Defaults to sync queue so auto works without Horizon.
 */
final class OpticalCustomerSync
{
    public static function dispatch(Customer $customer, bool $forceOltSync = false, bool $afterResponse = true): void
    {
        if (! config('optical.isp_digital_auto_sync', true)) {
            return;
        }

        $pending = SyncCustomerOnuFromOltJob::dispatch(
            (int) $customer->tenant_id,
            (int) $customer->id,
            $forceOltSync,
        );

        $connection = (string) config('optical.customer_sync_connection', 'sync');
        if ($connection !== '') {
            $pending->onConnection($connection);
        }

        if ($afterResponse) {
            $pending->afterResponse();
        }
    }
}
