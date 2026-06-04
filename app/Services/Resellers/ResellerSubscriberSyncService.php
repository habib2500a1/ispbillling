<?php

namespace App\Services\Resellers;

use App\Jobs\SyncCustomerNetworkAccessJob;
use App\Models\Customer;
use App\Models\Reseller;
use App\Services\Network\MikrotikNetworkProvisioner;
use App\Support\CustomerStatus;
use Illuminate\Support\Facades\Log;

/**
 * When admin turns a reseller OFF, suspend all active subscribers under them.
 * When turned ON again, restore only subscribers that were auto-held by this process.
 */
final class ResellerSubscriberSyncService
{
    public function isEnabled(): bool
    {
        return (bool) config('reseller.subscriber_sync.enabled', true);
    }

    /**
     * @return array{suspended: int, skipped: int}
     */
    public function suspendAllUnderReseller(Reseller $reseller): array
    {
        if (! $this->isEnabled()) {
            return ['suspended' => 0, 'skipped' => 0];
        }

        $suspended = 0;
        $skipped = 0;

        Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $reseller->tenant_id)
            ->where('reseller_id', $reseller->id)
            ->orderBy('id')
            ->chunkById(100, function ($customers) use (&$suspended, &$skipped): void {
                foreach ($customers as $customer) {
                    if ($this->holdSubscriber($customer)) {
                        $suspended++;
                    } else {
                        $skipped++;
                    }
                }
            });

        if ($suspended > 0) {
            Log::info('Reseller deactivated — subscribers suspended', [
                'reseller_id' => $reseller->id,
                'reseller_code' => $reseller->code,
                'suspended' => $suspended,
            ]);
        }

        return ['suspended' => $suspended, 'skipped' => $skipped];
    }

    /**
     * @return array{restored: int, skipped: int}
     */
    public function restoreResellerHeldSubscribers(Reseller $reseller): array
    {
        if (! $this->isEnabled()) {
            return ['restored' => 0, 'skipped' => 0];
        }

        $restored = 0;
        $skipped = 0;

        Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $reseller->tenant_id)
            ->where('reseller_id', $reseller->id)
            ->whereNotNull('meta->reseller_hold')
            ->orderBy('id')
            ->chunkById(100, function ($customers) use (&$restored, &$skipped): void {
                foreach ($customers as $customer) {
                    if ($this->releaseSubscriber($customer)) {
                        $restored++;
                    } else {
                        $skipped++;
                    }
                }
            });

        if ($restored > 0) {
            Log::info('Reseller activated — held subscribers restored', [
                'reseller_id' => $reseller->id,
                'reseller_code' => $reseller->code,
                'restored' => $restored,
            ]);
        }

        return ['restored' => $restored, 'skipped' => $skipped];
    }

    public function handleResellerActiveChange(Reseller $reseller, bool $wasActive, bool $isActive): void
    {
        if ($wasActive === $isActive) {
            return;
        }

        if (! $isActive) {
            $this->suspendAllUnderReseller($reseller);

            return;
        }

        $this->restoreResellerHeldSubscribers($reseller);
    }

    private function holdSubscriber(Customer $customer): bool
    {
        $meta = is_array($customer->meta) ? $customer->meta : [];

        if (isset($meta['reseller_hold'])) {
            return false;
        }

        if (CustomerStatus::isRestricted((string) $customer->status)) {
            return false;
        }

        $meta['reseller_hold'] = [
            'held_at' => now()->toIso8601String(),
            'previous_status' => (string) $customer->status,
            'previous_network_access_state' => (string) ($customer->network_access_state ?? 'active'),
            'reseller_id' => (int) $customer->reseller_id,
        ];

        $customer->forceFill([
            'meta' => $meta,
            'status' => CustomerStatus::SUSPENDED,
            'network_access_state' => 'suspended',
        ])->save();

        try {
            app(MikrotikNetworkProvisioner::class)->suspendCustomer($customer->fresh(), 'reseller-deactivated');
        } catch (\Throwable) {
            SyncCustomerNetworkAccessJob::dispatch((int) $customer->tenant_id, (int) $customer->id)->afterResponse();
        }

        return true;
    }

    private function releaseSubscriber(Customer $customer): bool
    {
        $meta = is_array($customer->meta) ? $customer->meta : [];
        $hold = $meta['reseller_hold'] ?? null;

        if (! is_array($hold)) {
            return false;
        }

        unset($meta['reseller_hold']);

        $status = CustomerStatus::normalize((string) ($hold['previous_status'] ?? CustomerStatus::ACTIVE));
        if (CustomerStatus::isRestricted($status)) {
            $status = CustomerStatus::ACTIVE;
        }

        $networkState = (string) ($hold['previous_network_access_state'] ?? 'active');
        if ($networkState === 'suspended') {
            $networkState = 'active';
        }

        $customer->forceFill([
            'meta' => $meta,
            'status' => $status,
            'network_access_state' => $networkState,
        ])->save();

        $fresh = $customer->fresh();
        if ($fresh === null) {
            return false;
        }

        try {
            if ($status === CustomerStatus::ACTIVE) {
                app(MikrotikNetworkProvisioner::class)->unsuspendCustomer($fresh);
                app(MikrotikNetworkProvisioner::class)->syncAccessPolicy($fresh);
            }
            SyncCustomerNetworkAccessJob::dispatch((int) $fresh->tenant_id, (int) $fresh->id)->afterResponse();
        } catch (\Throwable) {
            SyncCustomerNetworkAccessJob::dispatch((int) $fresh->tenant_id, (int) $fresh->id)->afterResponse();
        }

        return true;
    }
}
