<?php

namespace App\Support;

use App\Models\Customer;
use Illuminate\Support\Str;

/**
 * Match RouterOS PPP active session usernames to billing subscribers.
 */
final class CustomerPppLoginResolver
{
    /** @var array<int, array<string, Customer>> */
    private static array $tenantIndexCache = [];

    /** @var array<int, array<string, Customer>> serverId:login => customer */
    private static array $tenantServerIndexCache = [];

    public static function clearIndexCache(): void
    {
        self::$tenantIndexCache = [];
        self::$tenantServerIndexCache = [];
    }

    public static function normalize(string $login): string
    {
        $login = trim($login);
        if ($login === '') {
            return '';
        }

        if (str_contains($login, '@')) {
            $login = explode('@', $login, 2)[0];
        }

        if (str_contains($login, '\\')) {
            $parts = explode('\\', $login);

            $login = (string) end($parts);
        }

        return Str::lower(trim($login));
    }

    /**
     * @return array<string, Customer> normalized login => customer
     */
    public static function indexForTenant(int $tenantId): array
    {
        if (isset(self::$tenantIndexCache[$tenantId])) {
            return self::$tenantIndexCache[$tenantId];
        }

        $index = [];

        Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->select([
                'id', 'tenant_id', 'customer_code', 'phone', 'mikrotik_secret_name',
                'radius_username', 'status', 'network_access_state', 'package_id',
                'mikrotik_server_id',
            ])
            ->orderBy('id')
            ->chunkById(500, function ($customers) use (&$index): void {
                foreach ($customers as $customer) {
                    foreach (self::loginKeysForCustomer($customer) as $key) {
                        if ($key !== '') {
                            $index[$key] = $customer;
                        }
                    }
                }
            });

        return self::$tenantIndexCache[$tenantId] = $index;
    }

    /**
     * @return array<string, Customer> "{serverId}:{login}" => customer
     */
    public static function serverLoginIndexForTenant(int $tenantId): array
    {
        if (isset(self::$tenantServerIndexCache[$tenantId])) {
            return self::$tenantServerIndexCache[$tenantId];
        }

        $index = [];

        Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('mikrotik_server_id')
            ->select([
                'id', 'tenant_id', 'customer_code', 'phone', 'mikrotik_secret_name',
                'radius_username', 'status', 'network_access_state', 'package_id',
                'mikrotik_server_id',
            ])
            ->orderBy('id')
            ->chunkById(500, function ($customers) use (&$index): void {
                foreach ($customers as $customer) {
                    $serverId = (int) $customer->mikrotik_server_id;
                    if ($serverId <= 0) {
                        continue;
                    }
                    foreach (self::loginKeysForCustomer($customer) as $key) {
                        if ($key !== '') {
                            $index["{$serverId}:{$key}"] = $customer;
                        }
                    }
                }
            });

        return self::$tenantServerIndexCache[$tenantId] = $index;
    }

    public static function serverScopedKey(int $mikrotikServerId, string $normalizedLogin): string
    {
        return "{$mikrotikServerId}:{$normalizedLogin}";
    }

    /**
     * @return list<string>
     */
    public static function loginKeysForCustomer(Customer $customer): array
    {
        $keys = [];
        foreach ([$customer->mikrotik_secret_name, $customer->radius_username, $customer->customer_code] as $field) {
            if (filled($field)) {
                $keys[] = self::normalize((string) $field);
            }
        }

        $digits = preg_replace('/\D+/', '', (string) $customer->phone) ?? '';
        if ($digits !== '' && strlen($digits) >= 10) {
            $keys[] = $digits;
        }

        return array_values(array_unique($keys));
    }

    public static function resolve(int $tenantId, string $sessionUsername, ?int $mikrotikServerId = null): ?Customer
    {
        $raw = trim($sessionUsername);
        if ($raw === '') {
            return null;
        }

        $normalized = self::normalize($raw);
        $customer = null;

        if ($mikrotikServerId !== null && $mikrotikServerId > 0) {
            $customer = self::serverLoginIndexForTenant($tenantId)[self::serverScopedKey($mikrotikServerId, $normalized)] ?? null;
        }

        if ($customer === null) {
            $fallback = self::indexForTenant($tenantId)[$normalized] ?? null;
            if ($fallback !== null) {
                $homeServer = (int) ($fallback->mikrotik_server_id ?? 0);
                if ($mikrotikServerId === null || $mikrotikServerId <= 0 || $homeServer <= 0 || $homeServer === $mikrotikServerId) {
                    $customer = $fallback;
                }
            }
        }

        if ($customer !== null) {
            self::ensureLoginLinked($customer, $raw);
        }

        return $customer;
    }

    /**
     * Persist PPP login on subscriber when missing (fixes imports with auto-generated codes).
     */
    public static function ensureLoginLinked(Customer $customer, string $sessionUsername): void
    {
        $sessionUsername = trim($sessionUsername);
        if ($sessionUsername === '') {
            return;
        }

        $updates = [];

        if (blank($customer->mikrotik_secret_name)) {
            $updates['mikrotik_secret_name'] = $sessionUsername;
        }

        if (blank($customer->radius_username)) {
            $updates['radius_username'] = $sessionUsername;
        }

        if ($updates !== []) {
            $customer->forceFill($updates)->saveQuietly();
        }
    }
}
