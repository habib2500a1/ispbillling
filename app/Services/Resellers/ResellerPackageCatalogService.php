<?php

namespace App\Services\Resellers;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Reseller;
use App\Models\ResellerPackage;
use App\Services\Billing\PackagePriceResolver;
use Illuminate\Database\Eloquent\Collection;

/**
 * Packages a reseller may sell, customer bill price vs admin wholesale rate.
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

    public function assignmentFor(Reseller $reseller, Package|int $package): ?ResellerPackage
    {
        $packageId = $package instanceof Package ? (int) $package->id : $package;

        return ResellerPackage::query()
            ->where('reseller_id', $reseller->id)
            ->where('package_id', $packageId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Admin wholesale rate — what reseller pays admin per subscriber for this package.
     */
    public function wholesalePriceFor(Reseller $reseller, Package|int $package): ?float
    {
        $row = $this->assignmentFor($reseller, $package);
        if ($row === null) {
            return null;
        }

        if ($row->wholesale_price !== null && (float) $row->wholesale_price > 0) {
            return (float) $row->wholesale_price;
        }

        // Legacy rows: selling_price was previously used as a custom rate field.
        if ((float) $row->selling_price > 0) {
            return (float) $row->selling_price;
        }

        return null;
    }

    /**
     * Customer-facing monthly bill price (package list / zone / promo — not reseller wholesale).
     */
    public function customerBillPriceFor(Package $package, ?Customer $customer = null): float
    {
        return PackagePriceResolver::resolveBaseMonthlyPrice($package, $customer);
    }

    /** @deprecated Use wholesalePriceFor() or customerBillPriceFor() */
    public function sellingPriceFor(Reseller $reseller, Package|int $package): ?float
    {
        return $this->customerBillPriceFor(
            $package instanceof Package ? $package : Package::withoutGlobalScopes()->findOrFail($package),
        );
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
     * @return list<array{id: int, name: string, price_monthly: float, customer_price: float, wholesale_price: ?float}>
     */
    public function portalPackageOptions(Reseller $reseller): array
    {
        $packages = $this->packagesForReseller($reseller, true);

        return $packages->map(function (Package $package) use ($reseller): array {
            $customerPrice = $this->customerBillPriceFor($package);

            return [
                'id' => (int) $package->id,
                'name' => (string) $package->name,
                'price_monthly' => (float) $package->price_monthly,
                'customer_price' => $customerPrice,
                'wholesale_price' => $this->wholesalePriceFor($reseller, $package),
                // Back-compat for older views
                'selling_price' => $customerPrice,
            ];
        })->values()->all();
    }
}
