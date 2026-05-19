<?php

namespace App\Support;

use App\Models\Customer;

final class TenantResolver
{
    private static bool $usingFake = false;

    private static ?int $fakeTenantId = null;

    private static ?int $subdomainTenantId = null;

    private static ?int $customerTenantIdCache = null;

    private static int $authRecursionGuard = 0;

    public static function fake(?int $tenantId): void
    {
        self::$usingFake = true;
        self::$fakeTenantId = $tenantId;
    }

    public static function clearFake(): void
    {
        self::$usingFake = false;
        self::$fakeTenantId = null;
    }

    public static function setSubdomainTenantId(?int $id): void
    {
        self::$subdomainTenantId = $id;
    }

    /**
     * Clear fake tenant, subdomain hint, and test doubles between requests/tests.
     */
    public static function resetState(): void
    {
        self::$usingFake = false;
        self::$fakeTenantId = null;
        self::$subdomainTenantId = null;
        self::$customerTenantIdCache = null;
        self::$authRecursionGuard = 0;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutAuthRecursion(callable $callback): mixed
    {
        self::$authRecursionGuard++;

        try {
            return $callback();
        } finally {
            self::$authRecursionGuard--;
        }
    }

    public static function isAuthRecursionGuarded(): bool
    {
        return self::$authRecursionGuard > 0;
    }

    public static function currentTenantId(): ?int
    {
        if (self::$usingFake) {
            return self::$fakeTenantId;
        }

        if (auth('customer')->hasUser()) {
            $tid = auth('customer')->user()->tenant_id;

            return $tid !== null ? (int) $tid : null;
        }

        if (auth('customer')->check()) {
            return self::resolveCustomerTenantIdFromSession();
        }

        $user = auth('web')->user();
        if ($user && $user->tenant_id !== null) {
            return (int) $user->tenant_id;
        }

        if (self::$subdomainTenantId !== null) {
            return self::$subdomainTenantId;
        }

        return null;
    }

    public static function applyTenantScope(): bool
    {
        if (self::isAuthRecursionGuarded()) {
            return false;
        }

        return self::currentTenantId() !== null;
    }

    /**
     * Tenant id for queries when the current user has no tenant (e.g. super-admin).
     */
    public static function requiredTenantId(): int
    {
        return (int) (self::currentTenantId() ?? 1);
    }

    private static function resolveCustomerTenantIdFromSession(): ?int
    {
        if (self::$customerTenantIdCache !== null) {
            return self::$customerTenantIdCache;
        }

        $customerId = auth('customer')->id();
        if ($customerId === null) {
            return null;
        }

        $tid = Customer::withoutGlobalScopes()
            ->whereKey($customerId)
            ->value('tenant_id');

        self::$customerTenantIdCache = $tid !== null ? (int) $tid : null;

        return self::$customerTenantIdCache;
    }
}
