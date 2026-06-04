<?php

namespace App\Services\Resellers;

use App\Models\Customer;
use App\Models\Reseller;
use Illuminate\Validation\ValidationException;

final class ResellerQuotaService
{
    /**
     * @return array<string, mixed>
     */
    public function usage(Reseller $reseller): array
    {
        $customerCount = $reseller->customers()->count();
        $activeCustomers = $reseller->customers()->where('status', 'active')->count();
        $onuCount = $this->onuCount($reseller);
        $packageCount = $reseller->resellerPackages()->count();

        return [
            'customers' => $customerCount,
            'active_customers' => $activeCustomers,
            'onu' => $onuCount,
            'packages' => $packageCount,
            'limits' => [
                'max_clients' => $reseller->max_clients,
                'max_active_clients' => $reseller->max_active_clients,
                'max_onu' => $reseller->max_onu,
                'max_olt' => $reseller->max_olt,
                'max_packages' => $reseller->max_packages,
                'bandwidth_quota_mbps' => $reseller->bandwidth_quota_mbps,
            ],
        ];
    }

    public function assertCanAddCustomer(Reseller $reseller): void
    {
        if ($reseller->max_clients !== null && $reseller->customers()->count() >= (int) $reseller->max_clients) {
            throw ValidationException::withMessages([
                'quota' => 'Customer limit reached ('.$reseller->max_clients.'). Contact your parent reseller or admin.',
            ]);
        }
    }

    public function assertCanActivateCustomer(Reseller $reseller): void
    {
        if ($reseller->max_active_clients === null) {
            return;
        }

        $active = $reseller->customers()->where('status', 'active')->count();
        if ($active >= (int) $reseller->max_active_clients) {
            throw ValidationException::withMessages([
                'quota' => 'Active customer limit reached ('.$reseller->max_active_clients.').',
            ]);
        }
    }

    public function assertCanAddPackage(Reseller $reseller): void
    {
        if ($reseller->max_packages !== null && $reseller->resellerPackages()->count() >= (int) $reseller->max_packages) {
            throw ValidationException::withMessages(['quota' => 'Package quota limit reached.']);
        }
    }

    private function onuCount(Reseller $reseller): int
    {
        $customerIds = $reseller->customers()->pluck('id');
        if ($customerIds->isEmpty()) {
            return 0;
        }

        if (! class_exists(\App\Models\OnuDevice::class)) {
            return 0;
        }

        return (int) \App\Models\OnuDevice::query()
            ->whereIn('customer_id', $customerIds)
            ->count();
    }

    public function bandwidthUsageMbps(Reseller $reseller): float
    {
        return (float) Customer::query()
            ->where('reseller_id', $reseller->id)
            ->whereHas('package')
            ->join('packages', 'customers.package_id', '=', 'packages.id')
            ->sum('packages.download_mbps');
    }
}
