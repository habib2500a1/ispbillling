<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Services\Clients\ClientsDashboardService;
use App\Services\Network\NetworkAccessCoordinator;
use App\Support\CustomerStatus;
use App\Support\LegacyPortalSource;
use App\Support\SubscriberType;

/**
 * Align status, VIP/free type, and network state from stored legacy portal snapshots.
 */
final class LegacyPortalSubscriberLifecycleSyncService
{
    public function __construct(
        private readonly NetworkAccessCoordinator $network,
    ) {}

    /**
     * @return array{scanned: int, updated: int, suspended: int, reactivated: int, vip: int, free: int, expired: int, left: int, skipped: int}
     */
    public function syncAll(int $tenantId = 1, bool $dryRun = false, bool $syncNetwork = false): array
    {
        $stats = [
            'scanned' => 0,
            'updated' => 0,
            'suspended' => 0,
            'reactivated' => 0,
            'vip' => 0,
            'free' => 0,
            'expired' => 0,
            'left' => 0,
            'skipped' => 0,
        ];

        $importer = $this->importerForTenant($tenantId);

        Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->fromLegacyPortal()
            ->orderBy('customer_code')
            ->chunk(150, function ($customers) use ($importer, $dryRun, $syncNetwork, &$stats): void {
                foreach ($customers as $customer) {
                    $stats['scanned']++;
                    $result = $this->syncCustomer($customer, $importer, $dryRun, $syncNetwork);
                    if ($result === null) {
                        $stats['skipped']++;

                        continue;
                    }
                    if ($result['updated']) {
                        $stats['updated']++;
                    }
                    foreach (['suspended', 'reactivated', 'vip', 'free', 'expired', 'left'] as $key) {
                        if ($result[$key] ?? false) {
                            $stats[$key]++;
                        }
                    }
                }
            });

        if (! $dryRun) {
            ClientsDashboardService::flushSummaryCache($tenantId);
        }

        return $stats;
    }

    /**
     * @return array{updated: bool, suspended: bool, reactivated: bool, vip: bool, free: bool, expired: bool, left: bool}|null
     */
    public function syncCustomer(
        Customer $customer,
        ?LegacyPortalCustomerImporter $importer = null,
        bool $dryRun = false,
        bool $syncNetwork = false,
    ): ?array {
        $meta = is_array($customer->meta) ? $customer->meta : [];
        $raw = LegacyPortalSource::rawSnapshot($meta);
        if ($raw === []) {
            return null;
        }

        $importer ??= $this->importerForTenant((int) $customer->tenant_id);
        $attrs = $importer->lifecycleAttributesFromRow($raw);

        $flags = [
            'updated' => false,
            'suspended' => false,
            'reactivated' => false,
            'vip' => false,
            'free' => false,
            'expired' => false,
            'left' => false,
        ];

        $previousStatus = CustomerStatus::normalize((string) $customer->status);
        $nextStatus = CustomerStatus::normalize($attrs['status']);

        if ($previousStatus !== $nextStatus) {
            $flags['updated'] = true;
            if ($nextStatus === CustomerStatus::SUSPENDED) {
                $flags['suspended'] = true;
            }
            if ($previousStatus === CustomerStatus::SUSPENDED && $nextStatus === CustomerStatus::ACTIVE) {
                $flags['reactivated'] = true;
            }
            if ($nextStatus === CustomerStatus::EXPIRED) {
                $flags['expired'] = true;
            }
            if ($nextStatus === CustomerStatus::TERMINATED) {
                $flags['left'] = true;
            }
        }

        if (($customer->subscriber_type ?? '') !== $attrs['subscriber_type']) {
            $flags['updated'] = true;
            if ($attrs['subscriber_type'] === SubscriberType::VIP) {
                $flags['vip'] = true;
            }
            if ($attrs['subscriber_type'] === SubscriberType::FREE) {
                $flags['free'] = true;
            }
        }

        if (($customer->network_access_state ?? '') !== $attrs['network_access_state']
            || (bool) $customer->is_ppp_online !== $attrs['is_ppp_online']) {
            $flags['updated'] = true;
        }

        if (! $flags['updated']) {
            return $flags;
        }

        if ($dryRun) {
            return $flags;
        }

        $meta = LegacyPortalSource::rawSnapshotWithLegacyKey($meta);

        $customer->forceFill(array_merge($attrs, ['meta' => $meta]))->saveQuietly();

        if ($syncNetwork && in_array($nextStatus, [CustomerStatus::SUSPENDED, CustomerStatus::ACTIVE], true)) {
            $this->network->syncCustomer($customer->fresh() ?? $customer);
        }

        return $flags;
    }

    private function importerForTenant(int $tenantId): LegacyPortalCustomerImporter
    {
        return new LegacyPortalCustomerImporter($tenantId);
    }
}
