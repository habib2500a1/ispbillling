<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Services\Network\NetworkAccessCoordinator;
use App\Support\CustomerStatus;
use App\Support\LegacyPortalSource;

/**
 * Align local account status with legacy portal inactive/suspended rows.
 */
final class LegacyPortalSubscriberStatusReconciler
{
    public function __construct(
        private readonly NetworkAccessCoordinator $network,
    ) {}

    public function shouldBeSuspended(Customer $customer): bool
    {
        if (! LegacyPortalSource::isImportedSource($customer->import_source ?? null)) {
            return false;
        }

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $raw = LegacyPortalSource::rawSnapshot($meta);
        if ($raw === []) {
            return false;
        }

        if (filter_var($raw['Disabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $short = strtolower((string) ($raw['ShortStatus'] ?? ''));
        $status = strtolower((string) ($raw['Status'] ?? ''));

        return in_array($short, ['inactive', 'suspended', 'off'], true)
            || str_contains($status, 'inactive')
            || str_contains($status, 'suspend');
    }

    public function isAlreadySuspended(Customer $customer): bool
    {
        return CustomerStatus::normalize((string) $customer->status) === CustomerStatus::SUSPENDED
            && ($customer->network_access_state ?? '') === 'suspended';
    }

    public function reconcileOne(Customer $customer, bool $syncNetwork = true): bool
    {
        if (! $this->shouldBeSuspended($customer) || $this->isAlreadySuspended($customer)) {
            return false;
        }

        $customer->forceFill([
            'status' => CustomerStatus::SUSPENDED,
            'network_access_state' => 'suspended',
            'is_ppp_online' => false,
        ])->saveQuietly();

        if ($syncNetwork) {
            $this->network->syncCustomer($customer->fresh() ?? $customer);
        }

        return true;
    }

    /**
     * @return array{scanned: int, fixed: int, skipped: int}
     */
    public function reconcileMismatches(int $tenantId = 1, bool $syncNetwork = true): array
    {
        $scanned = 0;
        $fixed = 0;
        $skipped = 0;

        Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->fromLegacyPortal()
            ->where('status', CustomerStatus::ACTIVE)
            ->orderBy('customer_code')
            ->chunk(100, function ($customers) use ($syncNetwork, &$scanned, &$fixed, &$skipped): void {
                foreach ($customers as $customer) {
                    $scanned++;
                    if (! $this->shouldBeSuspended($customer)) {
                        $skipped++;

                        continue;
                    }
                    if ($this->reconcileOne($customer, $syncNetwork)) {
                        $fixed++;
                    }
                }
            });

        return ['scanned' => $scanned, 'fixed' => $fixed, 'skipped' => $skipped];
    }
}
