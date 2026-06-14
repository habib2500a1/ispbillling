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
     * @param  list<array<string, mixed>>  $tariffRows  TariffPackages from MAC reseller Details page.
     */
    public function syncFromTariffPackages(Reseller $reseller, array $tariffRows): int
    {
        $synced = 0;
        $wholesaleByPackage = [];
        $tariffMeta = [];

        foreach ($tariffRows as $row) {
            $packageId = $this->resolvePackageId([
                'PackageSpeed' => $row['ProfileSpeed'] ?? '',
                'Package' => $row['PackageName'] ?? '',
                'PackageId' => $row['PackageId'] ?? null,
            ]);

            if ($packageId === null) {
                continue;
            }

            $buy = round((float) ($row['PackageRate'] ?? 0), 2);
            $sell = round((float) ($row['SellingRate'] ?? 0), 2);
            $daily = round((float) ($row['DailyRate'] ?? 0), 2);

            $wholesaleByPackage[(string) $packageId] = $buy;
            $tariffMeta[] = [
                'package_id' => $packageId,
                'legacy_package_id' => (int) ($row['PackageId'] ?? 0),
                'package_name' => (string) ($row['PackageName'] ?? ''),
                'wholesale_monthly' => $buy,
                'selling_monthly' => $sell,
                'daily_rate' => $daily,
                'validity_days' => (int) ($row['ValidityDays'] ?? 30),
            ];

            if ($this->upsertAssignmentWithRates($reseller, $packageId, $buy, $sell)) {
                $synced++;
            }
        }

        if ($tariffMeta !== []) {
            $meta = is_array($reseller->meta) ? $reseller->meta : [];
            $meta['legacy_portal_tariff_packages'] = $tariffMeta;
            $meta['legacy_portal_package_wholesale'] = $wholesaleByPackage;
            $reseller->forceFill([
                'meta' => $meta,
                'commission_type' => 'percent',
                'commission_value' => $this->averageCommissionPercent($tariffMeta),
                'revenue_share_percent' => $this->averageCommissionPercent($tariffMeta),
            ])->saveQuietly();
        }

        return $synced;
    }

    private function averageCommissionPercent(array $tariffMeta): float
    {
        $percents = [];
        foreach ($tariffMeta as $row) {
            $sell = (float) ($row['selling_monthly'] ?? 0);
            $buy = (float) ($row['wholesale_monthly'] ?? 0);
            if ($sell > 0 && $buy >= 0) {
                $percents[] = max(0, round((($sell - $buy) / $sell) * 100, 2));
            }
        }

        if ($percents === []) {
            return 0.0;
        }

        return round(array_sum($percents) / count($percents), 2);
    }

    private function upsertAssignmentWithRates(Reseller $reseller, int $packageId, float $wholesale, float $selling): bool
    {
        $existing = ResellerPackage::query()
            ->where('reseller_id', $reseller->id)
            ->where('package_id', $packageId)
            ->first();

        $attrs = [
            'tenant_id' => $this->tenantId,
            'reseller_id' => $reseller->id,
            'package_id' => $packageId,
            'wholesale_price' => $wholesale,
            'selling_price' => $selling,
            'is_active' => true,
        ];

        if ($existing !== null) {
            $existing->forceFill($attrs)->saveQuietly();

            return false;
        }

        ResellerPackage::query()->create($attrs);

        return true;
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
