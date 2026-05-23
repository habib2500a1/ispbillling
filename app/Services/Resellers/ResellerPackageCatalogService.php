<?php

namespace App\Services\Resellers;

use App\Models\Package;
use App\Models\Reseller;
use App\Models\ResellerPackage;
use Illuminate\Database\Eloquent\Collection;

/**
 * Which packages a reseller may sell and at what selling price (BDT).
 */
final class ResellerPackageCatalogService
{
    /**
     * @return Collection<int, Package>
     */
    public function packagesForReseller(Reseller $reseller, bool $activeOnly = true): Collection
    {
        $tenantId = (int) $reseller->tenant_id;

        $assigned = ResellerPackage::query()
            ->where('reseller_id', $reseller->id)
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->pluck('package_id');

        $query = Package::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->when($activeOnly, fn ($q) => $q->where('is_active', true));

        if ($assigned->isNotEmpty()) {
            $query->whereIn('id', $assigned);
        }

        return $query->orderBy('name')->get();
    }

    public function sellingPriceFor(Reseller $reseller, Package|int $package): ?float
    {
        $packageId = $package instanceof Package ? (int) $package->id : $package;

        $row = ResellerPackage::query()
            ->where('reseller_id', $reseller->id)
            ->where('package_id', $packageId)
            ->where('is_active', true)
            ->first();

        if ($row !== null) {
            return (float) $row->selling_price;
        }

        $assignedCount = ResellerPackage::query()
            ->where('reseller_id', $reseller->id)
            ->count();

        if ($assignedCount > 0) {
            return null;
        }

        $pkg = Package::withoutGlobalScopes()->find($packageId);

        return $pkg !== null ? (float) $pkg->price_monthly : null;
    }

    public function resellerMaySellPackage(Reseller $reseller, int $packageId): bool
    {
        $assignedCount = ResellerPackage::query()
            ->where('reseller_id', $reseller->id)
            ->count();

        if ($assignedCount === 0) {
            return Package::withoutGlobalScopes()
                ->where('tenant_id', $reseller->tenant_id)
                ->where('id', $packageId)
                ->where('is_active', true)
                ->exists();
        }

        return ResellerPackage::query()
            ->where('reseller_id', $reseller->id)
            ->where('package_id', $packageId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * @return list<array{id: int, name: string, price_monthly: float, selling_price: float}>
     */
    public function portalPackageOptions(Reseller $reseller): array
    {
        $packages = $this->packagesForReseller($reseller, true);

        return $packages->map(function (Package $package) use ($reseller): array {
            $selling = $this->sellingPriceFor($reseller, $package) ?? (float) $package->price_monthly;

            return [
                'id' => (int) $package->id,
                'name' => (string) $package->name,
                'price_monthly' => (float) $package->price_monthly,
                'selling_price' => $selling,
            ];
        })->values()->all();
    }
}
