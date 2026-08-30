<?php

namespace App\Services\Saas;

use App\Models\SaasOperator;
use Illuminate\Support\Facades\Schema;

final class SaasDomain
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $host = strtolower(trim($value));
        $host = preg_replace('#^https?://#', '', $host) ?: '';
        $host = explode('/', $host)[0];
        $host = explode(':', $host)[0];
        $host = preg_replace('/^www\./', '', $host) ?: '';
        $host = trim($host, '.');

        if ($host === '' || ! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $host)) {
            return null;
        }

        return $host;
    }

    public static function reservedHosts(): array
    {
        $base = parse_url((string) config('app.url'), PHP_URL_HOST) ?: (string) config('app.url');
        $base = strtolower(preg_replace('/^www\./', '', (string) $base) ?: '');

        $hosts = array_filter([
            $base,
            $base !== '' ? 'www.'.$base : null,
            $base !== '' ? 'portal.'.$base : null,
            $base !== '' ? 'billing.'.$base : null,
            'localhost',
            '127.0.0.1',
        ]);

        return array_values(array_unique($hosts));
    }

    public static function isReserved(?string $host): bool
    {
        $normalized = self::normalize($host) ?? strtolower(trim((string) $host));

        return $normalized !== '' && in_array($normalized, self::reservedHosts(), true);
    }

    public static function findByHost(?string $host): ?SaasOperator
    {
        $normalized = self::normalize($host);
        if (! $normalized || ! Schema::hasTable('saas_operators') || ! Schema::hasColumn('saas_operators', 'domain')) {
            return null;
        }

        return SaasOperator::query()->where('domain', $normalized)->first();
    }

    public static function isRegistered(?string $host): bool
    {
        return self::findByHost($host) !== null;
    }

    /**
     * @return list<string>
     */
    public static function registeredHosts(): array
    {
        if (! Schema::hasTable('saas_operators') || ! Schema::hasColumn('saas_operators', 'domain')) {
            return [];
        }

        $hosts = [];
        foreach (SaasOperator::query()->whereNotNull('domain')->where('domain', '!=', '')->pluck('domain') as $domain) {
            $normalized = self::normalize((string) $domain);
            if (! $normalized) {
                continue;
            }
            $hosts[] = $normalized;
            $hosts[] = 'www.'.$normalized;
        }

        return array_values(array_unique($hosts));
    }

    public static function isAllowedHost(?string $host): bool
    {
        $raw = strtolower(trim((string) $host));
        if ($raw === '' || $raw === 'localhost' || $raw === '127.0.0.1' || $raw === '::1') {
            return true;
        }

        if (str_ends_with($raw, '.localhost') || ! str_contains($raw, '.')) {
            return true;
        }

        $normalized = self::normalize($raw) ?? $raw;
        if (in_array($normalized, self::reservedHosts(), true) || in_array($raw, self::reservedHosts(), true)) {
            return true;
        }

        if (in_array($raw, self::registeredHosts(), true) || in_array($normalized, self::registeredHosts(), true)) {
            return true;
        }

        return false;
    }

    public static function serverIpHint(): string
    {
        if ($configured = env('SAAS_ORIGIN_IP')) {
            return $configured;
        }

        return '204.136.10.31';
    }
}
