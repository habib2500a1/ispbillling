<?php

namespace App\Services\Portal;

use App\Models\PortalMarquee;
use App\Models\PortalNotice;
use App\Models\Tenant;
use App\Support\TenantResolver;
use Illuminate\Support\Collection;

final class PortalContentCatalog
{
    /**
     * @return Collection<int, PortalNotice>
     */
    public static function noticesForLanding(): Collection
    {
        return static::noticesQuery(static::resolveTenantId())
            ->forLanding()
            ->ordered()
            ->get();
    }

    /**
     * @return Collection<int, PortalNotice>
     */
    public static function noticesForPortal(?int $tenantId = null): Collection
    {
        $tenantId ??= TenantResolver::currentTenantId();

        if ($tenantId === null) {
            return collect();
        }

        return static::noticesQuery($tenantId)
            ->forPortal()
            ->ordered()
            ->get();
    }

    /**
     * @return Collection<int, PortalMarquee>
     */
    public static function marqueeForLanding(): Collection
    {
        return static::marqueeQuery(static::resolveTenantId())
            ->forLanding()
            ->ordered()
            ->get();
    }

    /**
     * @return Collection<int, PortalMarquee>
     */
    public static function marqueeForPortal(?int $tenantId = null): Collection
    {
        $tenantId ??= TenantResolver::currentTenantId();

        if ($tenantId === null) {
            return collect();
        }

        return static::marqueeQuery($tenantId)
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

    private static function noticesQuery(int $tenantId): \Illuminate\Database\Eloquent\Builder
    {
        return PortalNotice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId);
    }

    private static function marqueeQuery(int $tenantId): \Illuminate\Database\Eloquent\Builder
    {
        return PortalMarquee::withoutGlobalScopes()
            ->where('tenant_id', $tenantId);
    }
}
