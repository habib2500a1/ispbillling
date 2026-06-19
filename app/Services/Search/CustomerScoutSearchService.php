<?php

namespace App\Services\Search;

use App\Models\Customer;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\Log;

/**
 * Laravel Scout + Meilisearch customer lookup (typo-tolerant, multi-field).
 * Returns null on failure so callers can fall back to SQL search.
 */
final class CustomerScoutSearchService
{
    public function enabled(): bool
    {
        if (! class_exists(\Laravel\Scout\EngineManager::class)) {
            return false;
        }

        return \App\Support\CustomerSearchSettings::useScout();
    }

    /**
     * @return list<int>|null Ordered customer IDs, or null to use SQL fallback.
     */
    public function searchIds(string $query, int $limit = 75, ?int $tenantId = null): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $tenantId ??= TenantResolver::applyTenantScope()
            ? TenantResolver::currentTenantId()
            : null;

        try {
            $builder = Customer::search($query)->take(max(1, $limit));

            if ($tenantId !== null) {
                $builder->where('tenant_id', $tenantId);
            }

            $ids = $builder
                ->keys()
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            if ($ids === [] && ctype_digit($query)) {
                $exact = Customer::query()
                    ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                    ->whereKey((int) $query)
                    ->value('id');

                if ($exact !== null) {
                    return [(int) $exact];
                }
            }

            return $ids;
        } catch (\Throwable $e) {
            Log::warning('customer_scout_search_failed', [
                'query' => $query,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return config('customer_search.sql_fallback', true) ? null : [];
        }
    }
}
