<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\Network\NetworkAccessCoordinator;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Re-runs network access policy for one customer (MikroTik PPP, RADIUS stubs, etc.).
 *
 * Use dispatchSync() after payments so PPP enable/disable happens in the same request (~1s).
 */
class SyncCustomerNetworkAccessJob
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $tenantId,
        public int $customerId,
    ) {}

    public function handle(NetworkAccessCoordinator $coordinator): void
    {
        try {
            $customer = Customer::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $this->tenantId)
                ->find($this->customerId);

            if (! $customer) {
                return;
            }

            $coordinator->syncCustomer($customer);
        } catch (\Throwable $e) {
            Log::channel('single')->error('network.sync_customer_job_failed', [
                'tenant_id' => $this->tenantId,
                'customer_id' => $this->customerId,
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }
}
