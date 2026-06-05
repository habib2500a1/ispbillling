<?php

namespace App\Services\Clients;

use App\Models\Customer;
use App\Models\Package;
use App\Services\Billing\BillingAccountListCounts;
use App\Support\CustomerAccountScopes;
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
     * Lightweight counts for the subscribers list (tabs + stat cards). Avoids billing sidebar aggregates.
     *
     * @return array{total: int, active: int, online: int, offline: int, home: int, reseller: int}
     */
    public function listPresetSummary(): array
    {
        $tenantId = TenantResolver::requiredTenantId();

        return Cache::remember(
            'clients_list_preset_summary:'.$tenantId,
            120,
            function () use ($tenantId): array {
                $bandwidth = app(\App\Services\Bandwidth\BandwidthCollectionService::class);
                $base = Customer::query()->where('tenant_id', $tenantId);
                $notTerminated = (clone $base)->where('status', '!=', CustomerStatus::TERMINATED);
                $total = (int) (clone $notTerminated)->count();
                $online = $bandwidth->displayedOnlineCount($tenantId, clone $notTerminated);
                $homePackageIds = $this->homePackageIds($tenantId);

                $homeQuery = (clone $notTerminated)->whereNotNull('package_id');
                if ($homePackageIds !== []) {
                    $homeQuery->whereIn('package_id', $homePackageIds);
                } else {
                    $homeQuery->whereRaw('0 = 1');
                }

                return [
                    'total' => $total,
                    'active' => (int) CustomerAccountScopes::applyActive(clone $base)->count(),
                    'online' => $online,
                    'offline' => max(0, $total - $online),
                    'home' => (int) $homeQuery->count(),
                    'reseller' => (int) (clone $notTerminated)->whereNotNull('reseller_id')->count(),
                ];
            },
        );
    }

    /**
     * @return array<string, int>
     */
    public function summary(?Builder $scopedQuery = null): array
    {
        $tenantId = TenantResolver::requiredTenantId();

        return Cache::remember(
            'clients_dashboard_summary:'.$tenantId.':'.md5((string) ($scopedQuery?->toRawSql() ?? 'all')),
            120,
            function () use ($scopedQuery, $tenantId): array {
                $base = $scopedQuery ?? Customer::query()->where('tenant_id', $tenantId);
                $notTerminated = fn () => (clone $base)->where('status', '!=', CustomerStatus::TERMINATED);
                $homePackageIds = $this->homePackageIds($tenantId);
                $online = app(\App\Services\Bandwidth\BandwidthCollectionService::class)
                    ->displayedOnlineCount($tenantId, clone $notTerminated());
                $active = CustomerAccountScopes::applyActive(clone $base)->count();
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
                    'left' => CustomerAccountScopes::applyLeft(clone $base)->count(),
                ];
            },
        );
    }

    public static function flushSummaryCache(int $tenantId): void
    {
        Cache::forget('clients_list_preset_summary:'.$tenantId);
        Cache::forget('clients_dashboard_summary:'.$tenantId.':'.md5('all'));
        Cache::forget('clients_directory_stats:'.$tenantId);
        Cache::forget('ppp_active_session_customer_ids:'.$tenantId);
        Cache::forget('tenant_open_invoice_due_sum:'.$tenantId);
        Cache::forget('staff_billing_kpi:'.$tenantId);
        Cache::forget('staff_billing_due_clients:'.$tenantId);
        Cache::forget('clients_filter_packages:'.$tenantId);
        Cache::forget('clients_filter_zones:'.$tenantId);
        Cache::forget('clients_filter_resellers:'.$tenantId);
    }
}
