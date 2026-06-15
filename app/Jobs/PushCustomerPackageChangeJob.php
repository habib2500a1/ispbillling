<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\Network\MikrotikNetworkProvisioner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Push updated PPP profile to MikroTik and kick active sessions so the new speed applies on reconnect.
 */
class PushCustomerPackageChangeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public int $tenantId,
        public int $customerId,
    ) {
        $this->onQueue('network');
    }

    public function handle(MikrotikNetworkProvisioner $provisioner): void
    {
        try {
            $customer = Customer::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $this->tenantId)
                ->find($this->customerId);

            if ($customer === null) {
                return;
            }

            $provisioner->pushPackageChange($customer);

            Log::channel('single')->info('network.package_change_pushed', [
                'tenant_id' => $this->tenantId,
                'customer_id' => $this->customerId,
                'package_id' => $customer->package_id,
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->error('network.package_change_push_failed', [
                'tenant_id' => $this->tenantId,
                'customer_id' => $this->customerId,
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }
}
