<?php

namespace App\Services\Portal;

use App\Models\PortalMovieServer;
use App\Models\Tenant;
use App\Support\TenantResolver;
use Illuminate\Support\Collection;

final class PortalMovieServerCatalog
{
    /**
     * @return Collection<int, PortalMovieServer>
     */
    public static function forLanding(): Collection
    {
        return static::queryForTenant(static::resolveTenantId())
            ->forLanding()
            ->ordered()
            ->get();
    }

    /**
     * @return Collection<int, PortalMovieServer>
     */
    public static function forPortal(?int $tenantId = null): Collection
    {
        $tenantId ??= TenantResolver::currentTenantId();

        if ($tenantId === null) {
            return collect();
        }

        return static::queryForTenant($tenantId)
            ->forPortal()
            ->ordered()
            ->get();
    }

    private static function resolveTenantId(): int
    {
        return (int) (TenantResolver::currentTenantId()
            ?? Tenant::query()->orderBy('id')->value('id')
            ?? 1);
    }

    private static function queryForTenant(int $tenantId): \Illuminate\Database\Eloquent\Builder
    {
        return PortalMovieServer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId);
    }
}
