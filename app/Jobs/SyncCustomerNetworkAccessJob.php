<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\Network\NetworkAccessCoordinator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Re-runs network access policy for one customer (MikroTik PPP suspend/reconnect, RADIUS, etc.).
 *
 * Dispatched to the `network` queue when QUEUE_HEAVY_JOBS_ENABLED=true so bulk suspend/reconnect
 * does not block the web app or cron process on router API latency.
 */
class SyncCustomerNetworkAccessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    public function __construct(
        public int $tenantId,
        public int $customerId,
    ) {
        $this->onQueue('network');
    }

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

            throw $e;
        }
    }
}
