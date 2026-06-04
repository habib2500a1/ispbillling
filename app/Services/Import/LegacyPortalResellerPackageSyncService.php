<?php

namespace App\Services\Import;

use App\Models\Package;
use App\Models\Reseller;
use App\Models\ResellerPackage;
/**
 * Assign packages + wholesale rates for a reseller from legacy portal MAC client rows.
 */
final class LegacyPortalResellerPackageSyncService
{
    public function __construct(
        private readonly int $tenantId = 1,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $clientRows
     */
    public function syncFromClientRows(Reseller $reseller, array $clientRows): int
    {
        $packageIds = [];

        foreach ($clientRows as $row) {
            $packageId = $this->resolvePackageId($row);
            if ($packageId !== null) {
                $packageIds[$packageId] = true;
            }
        }

        $synced = 0;
        foreach (array_keys($packageIds) as $packageId) {
            if ($this->upsertAssignment($reseller, (int) $packageId)) {
                $synced++;
            }
        }

        return $synced;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolvePackageId(array $row): ?int
    {
        $mapped = [
            'PackageSpeed' => $row['PackageSpeed'] ?? '',
            'Package' => is_string($row['PackageSpeed'] ?? null)
                ? strtok((string) $row['PackageSpeed'], '/')
                : ($row['PackageName'] ?? ''),
        ];

        return (new LegacyPortalCustomerImporter($this->tenantId))->resolvePackageIdForRow($mapped);
    }

    private function upsertAssignment(Reseller $reseller, int $packageId): bool
    {
        $package = Package::query()->find($packageId);
        if ($package === null) {
            return false;
        }

        $wholesale = $this->resolveWholesalePrice($reseller, $package);

        $existing = ResellerPackage::query()
            ->where('reseller_id', $reseller->id)
            ->where('package_id', $packageId)
            ->first();

        $attrs = [
            'tenant_id' => $this->tenantId,
            'reseller_id' => $reseller->id,
            'package_id' => $packageId,
            'wholesale_price' => $wholesale,
            'selling_price' => 0,
            'is_active' => true,
        ];

        if ($existing !== null) {
            $existing->forceFill($attrs)->saveQuietly();

            return false;
        }

        ResellerPackage::query()->create($attrs);

        return true;
    }

    private function resolveWholesalePrice(Reseller $reseller, Package $package): float
    {
        $meta = is_array($reseller->meta) ? $reseller->meta : [];
        $rates = is_array($meta['legacy_portal_package_wholesale'] ?? null) ? $meta['legacy_portal_package_wholesale'] : [];

        if (isset($rates[(string) $package->id])) {
            return round((float) $rates[(string) $package->id], 2);
        }

        $percent = max(0, min(100, (float) ($reseller->commission_value ?? 0)));
        $retail = (float) ($package->price_monthly ?? 0);

        if ($percent > 0.009 && $retail > 0) {
            return round($retail * (1 - $percent / 100), 2);
        }

        return round(max(0, $retail), 2);
    }
}
