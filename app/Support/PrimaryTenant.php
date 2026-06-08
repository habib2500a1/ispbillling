<?php

namespace App\Support;

use App\Models\Tenant;

final class PrimaryTenant
{
    public static function id(): int
    {
        return (int) config('isp.default_tenant_id', 1);
    }

    public static function isPrimary(int|string|null $tenantId): bool
    {
        if ($tenantId === null || $tenantId === '') {
            return false;
        }

        return (int) $tenantId === self::id();
    }

    public static function model(): ?Tenant
    {
        return Tenant::query()->find(self::id());
    }

    public static function allowsRollback(int|string|null $tenantId): bool
    {
        return ! self::isPrimary($tenantId);
    }
}
