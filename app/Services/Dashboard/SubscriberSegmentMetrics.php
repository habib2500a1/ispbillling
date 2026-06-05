<?php

namespace App\Services\Dashboard;

use App\Models\Customer;
use App\Models\Reseller;
use App\Services\Bandwidth\BandwidthCollectionService;
use App\Support\CustomerStatus;
use Illuminate\Support\Facades\Cache;

/**
 * Split subscriber KPIs: direct (own) vs reseller-attached clients vs reseller partners.
 */
final class SubscriberSegmentMetrics
{
    /**
     * @return array{
     *     active_subscribers: int,
     *     direct_active: int,
     *     reseller_clients_active: int,
     *     active_reseller_partners: int,
     *     online_now: int,
     *     direct_online: int,
     *     reseller_clients_online: int
     * }
     */
    public function forTenant(int $tenantId): array
    {
        return Cache::remember(
            "dashboard:subscriber_segments:{$tenantId}",
            now()->addSeconds((int) config('dashboard.snapshot_cache_seconds', 45)),
            fn (): array => $this->build($tenantId),
        );
    }

    /**
     * @return array{
     *     active_subscribers: int,
     *     direct_active: int,
     *     reseller_clients_active: int,
     *     active_reseller_partners: int,
     *     online_now: int,
     *     direct_online: int,
     *     reseller_clients_online: int
     * }
     */
    private function build(int $tenantId): array
    {
        $active = CustomerStatus::ACTIVE;

        $row = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->selectRaw(
                <<<'SQL'
                COUNT(*) FILTER (WHERE status = ?) as active_subscribers,
                COUNT(*) FILTER (WHERE status = ? AND reseller_id IS NULL) as direct_active,
                COUNT(*) FILTER (WHERE status = ? AND reseller_id IS NOT NULL) as reseller_clients_active
                SQL,
                [$active, $active, $active],
            )
            ->first();

        $bandwidth = app(BandwidthCollectionService::class);
        $activeBase = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', $active);

        $directOnline = $bandwidth->displayedOnlineCount(
            $tenantId,
            (clone $activeBase)->whereNull('reseller_id'),
        );
        $resellerOnline = $bandwidth->displayedOnlineCount(
            $tenantId,
            (clone $activeBase)->whereNotNull('reseller_id'),
        );

        return [
            'active_subscribers' => (int) ($row->active_subscribers ?? 0),
            'direct_active' => (int) ($row->direct_active ?? 0),
            'reseller_clients_active' => (int) ($row->reseller_clients_active ?? 0),
            'active_reseller_partners' => Reseller::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->count(),
            'online_now' => $directOnline + $resellerOnline,
            'direct_online' => $directOnline,
            'reseller_clients_online' => $resellerOnline,
        ];
    }
}
