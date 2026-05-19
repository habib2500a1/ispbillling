<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\Optical\IspDigitalOnuPipelineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncCustomerOnuFromOltJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public int $tenantId,
        public int $customerId,
        public bool $forceOltSync = false,
    ) {}

    public function handle(IspDigitalOnuPipelineService $pipeline): void
    {
        if (! config('optical.isp_digital_auto_sync', true)) {
            return;
        }

        $customer = Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->find($this->customerId);

        if ($customer === null) {
            return;
        }

        try {
            $pipeline->syncAndLinkCustomer($customer, $this->forceOltSync);
        } catch (\Throwable $e) {
            Log::warning('isp_digital_onu.customer_sync_failed', [
                'customer_id' => $this->customerId,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
