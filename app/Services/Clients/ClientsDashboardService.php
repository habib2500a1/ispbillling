<?php

namespace App\Services\Clients;

use App\Models\Customer;
use App\Models\Package;
use App\Services\Billing\BillingAccountListCounts;
use App\Support\CustomerStatus;
use App\Support\TenantResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

final class ClientsDashboardService
{
    /**
     * @return list<int>
     */
    private function homePackageIds(int $tenantId): array
    {
        return Cache::remember(
            'clients_home_package_ids:'.$tenantId,
            300,
            fn (): array => Package::query()
                ->where('tenant_id', $tenantId)
                ->where('type', '!=', 'hotspot')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
        );
    }

    /**
     * @return array<string, int>
     */
    public function summary(?Builder $scopedQuery = null): array
    {
        $tenantId = TenantResolver::currentTenantId() ?? 0;

        return Cache::remember(
            'clients_dashboard_summary:'.$tenantId.':'.md5((string) ($scopedQuery?->toRawSql() ?? 'all')),
            120,
            function () use ($scopedQuery): array {
                $base = $scopedQuery ?? Customer::query();
                $notTerminated = fn () => (clone $base)->where('status', '!=', CustomerStatus::TERMINATED);
                $tenantId = TenantResolver::currentTenantId() ?? 0;
                $homePackageIds = $this->homePackageIds($tenantId);

                $online = (clone $notTerminated())->where('is_ppp_online', true)->count();
                $active = (clone $base)->where('status', CustomerStatus::ACTIVE)->count();
                $total = (clone $notTerminated())->count();

                $homeQuery = (clone $notTerminated())->whereNotNull('package_id');
                if ($homePackageIds !== []) {
                    $homeQuery->whereIn('package_id', $homePackageIds);
                } else {
                    $homeQuery->whereRaw('0 = 1');
                }

                return [
                    'total' => $total,
                    'active' => $active,
                    'online' => $online,
                    'offline' => max(0, $total - $online),
                    'home' => $homeQuery->count(),
                    'reseller' => (clone $notTerminated())->whereNotNull('reseller_id')->count(),
                    'suspended' => (clone $base)->where('status', CustomerStatus::SUSPENDED)->count(),
                    'expired' => app(BillingAccountListCounts::class)->get('expired'),
                    'left' => (clone $base)->where('status', CustomerStatus::TERMINATED)->count(),
                ];
            },
        );
    }
}
