<?php

namespace App\Support;

/**
 * Tenant id for public routes (landing, shop, hotspot) when no logged-in user exists.
 */
final class PublicTenantContext
{
    public static function tenantId(): int
    {
        return (int) (TenantResolver::currentTenantId() ?? config('isp.default_tenant_id', 1));
    }
}
