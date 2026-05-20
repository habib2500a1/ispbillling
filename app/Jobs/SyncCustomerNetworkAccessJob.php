<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\Network\MikrotikNetworkProvisioner;
use App\Services\Network\NetworkAccessCoordinator;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Re-runs network access policy for one customer (MikroTik PPP, RADIUS stubs, etc.).
 *
 * Runs synchronously when dispatched so PPP secrets update without a queue worker.
 */
class SyncCustomerNetworkAccessJob
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $tenantId,
        public int $customerId,
    ) {}

    public function handle(
        NetworkAccessCoordinator $coordinator,
        MikrotikNetworkProvisioner $mikrotikProvisioner,
    ): void {
        try {
            $customer = Customer::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $this->tenantId)
                ->find($this->customerId);

            if (! $customer) {
                return;
            }

            $coordinator->syncCustomer($customer);

            $mtPush = (bool) config('network.mikrotik_push_enabled', true);
            $alwaysMt = (bool) config('network.mikrotik_always_push_ppp_on_customer_save', true);

            if ($mtPush && $alwaysMt) {
                $fresh = $customer->fresh() ?? $customer;
                if (filled($fresh->mikrotik_secret_name) || filled($fresh->radius_username)) {
                    $mikrotikProvisioner->syncAccessPolicy($fresh);
                }
            }
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
