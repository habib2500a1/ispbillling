<?php

namespace App\Services\Subscribers;

use App\Jobs\SyncCustomerNetworkAccessJob;
use App\Models\Customer;

final class CustomerServiceRenewalService
{
    /**
     * Extend service expiry and re-enable network (PHPNuxBill-style quick recharge).
     *
     * @return array{expires_at: string, days: int}
     */
    public function extendDays(Customer $customer, int $days, bool $syncNetwork = true): array
    {
        $days = max(1, min(730, $days));

        $base = $customer->service_expires_at && $customer->service_expires_at->isFuture()
            ? $customer->service_expires_at->copy()->startOfDay()
            : now()->startOfDay();

        $customer->forceFill([
            'service_expires_at' => $base->copy()->addDays($days)->toDateString(),
            'status' => 'active',
            'network_access_state' => 'active',
        ])->save();

        if ($syncNetwork) {
            SyncCustomerNetworkAccessJob::dispatch((int) $customer->tenant_id, (int) $customer->id)->afterResponse();
        }

        $fresh = $customer->fresh();

        return [
            'expires_at' => (string) $fresh?->service_expires_at?->toDateString(),
            'days' => $days,
        ];
    }
}
