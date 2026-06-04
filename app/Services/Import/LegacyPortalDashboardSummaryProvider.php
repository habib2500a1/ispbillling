<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Support\BillingMetricsCache;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Dashboard KPI totals from legacy portal GetBillingListOtherData (pay.anetbd.com).
 */
final class LegacyPortalDashboardSummaryProvider
{
    private const CACHE_KEY = 'legacy_portal:billing_summary:';

    private const CACHE_TTL_MINUTES = 15;

    public function tenantUsesLegacyPortal(int $tenantId): bool
    {
        return Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->fromLegacyPortal()
            ->exists();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function summary(int $tenantId, bool $allowRemoteRefresh = true): ?array
    {
        $cached = $this->readCache($tenantId);
        if ($cached !== null && $this->isFresh($cached)) {
            return $cached;
        }

        if (! $allowRemoteRefresh) {
            return $cached;
        }

        $refreshed = $this->refreshFromRemote($tenantId);

        return $refreshed ?? $cached;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function refreshFromRemote(int $tenantId): ?array
    {
        $password = (string) config('legacy_portal.password', '');
        if ($password === '') {
            return null;
        }

        $lockKey = "legacy_portal:billing_summary_refresh:{$tenantId}";

        return Cache::lock($lockKey, 120)->block(5, function () use ($tenantId, $password): ?array {
            $cached = $this->readCache($tenantId);
            if ($cached !== null && $this->isFresh($cached)) {
                return $cached;
            }

            try {
                $client = new LegacyPortalSessionClient(
                    (string) config('legacy_portal.base_url'),
                    (string) config('legacy_portal.username'),
                    $password,
                );
                $client->login();
                $summary = $client->fetchBillingListOtherData();
                $this->writeCache($tenantId, $summary);
                BillingMetricsCache::flush($tenantId);

                return $this->readCache($tenantId);
            } catch (Throwable) {
                return $cached;
            }
        });
    }

    /**
     * @param  array<string, float|int>  $summary
     */
    public function storeSummary(int $tenantId, array $summary): void
    {
        $this->writeCache($tenantId, $summary);
        BillingMetricsCache::flush($tenantId);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function writeCache(int $tenantId, array $summary): void
    {
        Cache::put(
            self::CACHE_KEY.$tenantId,
            array_merge($summary, ['synced_at' => now()->toIso8601String()]),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readCache(int $tenantId): ?array
    {
        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get(self::CACHE_KEY.$tenantId);

        return $cached;
    }

    /**
     * @param  array<string, mixed>  $cached
     */
    private function isFresh(array $cached): bool
    {
        $syncedAt = $cached['synced_at'] ?? null;
        if (! is_string($syncedAt) || $syncedAt === '') {
            return isset($cached['monthly_bill'], $cached['collected_bill'], $cached['due']);
        }

        try {
            return now()->diffInMinutes(\Illuminate\Support\Carbon::parse($syncedAt)) < self::CACHE_TTL_MINUTES;
        } catch (Throwable) {
            return false;
        }
    }
}
