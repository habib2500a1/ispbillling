<?php

namespace App\Services\Radius;

use App\Models\Customer;
use Illuminate\Support\Facades\Log;

final class CustomerRadiusSyncService
{
    public function __construct(
        private readonly RadiusUserManagementService $radius,
    ) {}

    public function sync(Customer $customer): void
    {
        if (! $this->radius->isAvailable()) {
            return;
        }

        if (! filled($customer->radius_username) && ! filled($customer->customer_code)) {
            return;
        }

        try {
            $this->radius->ensureCustomerUser($customer);

            $package = $customer->package;
            if ($package !== null && (int) $package->download_mbps > 0) {
                $this->radius->setDownloadRateLimit(
                    $this->radius->usernameForCustomer($customer),
                    (int) $package->download_mbps,
                );
            }
        } catch (\Throwable $e) {
            Log::warning('radius.customer_sync_failed', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
