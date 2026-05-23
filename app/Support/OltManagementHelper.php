<?php

namespace App\Support;

use App\Models\Device;

final class OltManagementHelper
{
    public const META_WEB_URL = 'olt_web_url';

    public const META_WEB_USERNAME = 'olt_web_username';

    public const META_WEB_PASSWORD = 'olt_web_password';

    /**
     * Strip scheme/path from pasted URLs (e.g. http://103.29.127.94:8506).
     */
    public static function normalizeManagementIp(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('#^https?://([^/]+)#i', $value, $m)) {
            $value = $m[1];
        }

        if (str_contains($value, ':') && ! filter_var($value, FILTER_VALIDATE_IP)) {
            $value = explode(':', $value, 2)[0];
        }

        return $value;
    }

    public static function defaultAveisWebUrl(string $managementIp): string
    {
        $port = (int) config('olt_drivers.aveis_web_port', 8506);

        return $managementIp.':'.$port;
    }

    public static function isAveisDriver(?string $oltDriver): bool
    {
        $driver = strtolower((string) $oltDriver);

        return str_starts_with($driver, 'aveis_');
    }

    public static function webUiUrl(Device $olt): ?string
    {
        $meta = is_array($olt->meta) ? $olt->meta : [];
        $raw = trim((string) ($meta[self::META_WEB_URL] ?? ''));

        if ($raw === '' && filled($olt->management_ip) && self::isAveisDriver($olt->olt_driver)) {
            $raw = self::defaultAveisWebUrl((string) $olt->management_ip);
        }

        if ($raw === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $raw)) {
            return $raw;
        }

        return 'http://'.$raw;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public static function mergeWebCredentialsIntoMeta(array $meta, ?string $url, ?string $username, ?string $password): array
    {
        if ($url !== null && trim($url) !== '') {
            $meta[self::META_WEB_URL] = trim($url);
        }

        if ($username !== null && trim($username) !== '') {
            $meta[self::META_WEB_USERNAME] = trim($username);
        }

        if ($password !== null && $password !== '') {
            $meta[self::META_WEB_PASSWORD] = encrypt($password);
        }

        return $meta;
    }

    public static function webPasswordFromMeta(array $meta): ?string
    {
        $enc = $meta[self::META_WEB_PASSWORD] ?? null;
        if (! is_string($enc) || $enc === '') {
            return null;
        }

        try {
            return decrypt($enc);
        } catch (\Throwable) {
            return null;
        }
    }
}
