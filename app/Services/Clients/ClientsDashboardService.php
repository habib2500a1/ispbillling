<?php

namespace App\Services\Clients;

use App\Models\Customer;
use App\Services\Billing\BillingAccountListCounts;
use App\Support\CustomerStatus;
use App\Support\TenantResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

final class ClientsDashboardService
{
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

                $online = (clone $notTerminated())->where('is_ppp_online', true)->count();
                $active = (clone $base)->where('status', CustomerStatus::ACTIVE)->count();
                $total = (clone $notTerminated())->count();

                return [
                    'total' => $total,
                    'active' => $active,
                    'online' => $online,
                    'offline' => max(0, $total - $online),
                    'home' => (clone $notTerminated())
                        ->whereNotNull('package_id')
                        ->whereHas('package', fn (Builder $q): Builder => $q->where('type', '!=', 'hotspot'))
                        ->count(),
                    'reseller' => (clone $notTerminated())->whereNotNull('reseller_id')->count(),
                    'suspended' => (clone $base)->where('status', CustomerStatus::SUSPENDED)->count(),
                    'expired' => app(BillingAccountListCounts::class)->get('expired'),
                    'left' => (clone $base)->where('status', CustomerStatus::TERMINATED)->count(),
                ];
            },
        );
    }
}
