<?php

namespace App\Services\Mobile;

use App\Support\MobileAppLinks;

/**
 * Login hub metadata for web + mobile — same roles and labels as /login.
 */
class MobileLoginHubConfig
{
    /**
     * @return array<string, mixed>
     */
    public static function payload(): array
    {
        $base = rtrim((string) config('app.url'), '/');

        return [
            'hub_path' => '/login',
            'hub_url' => route('login.hub'),
            'api_path' => '/api/v1/mobile/login',
            'api_url' => $base.'/api/v1/mobile/login',
            'roles' => array_values(array_filter([
                self::customerRole($base),
                self::staffRole($base),
                self::resellerRole($base),
            ])),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function customerRole(string $base): ?array
    {
        if (! config('portal.enabled', true)) {
            return null;
        }

        return [
            'id' => 'customer',
            'label' => 'Customer portal',
            'description' => 'Bills, usage, speed test, tickets, and your connection',
            'enabled' => true,
            'mode' => 'native',
            'web_path' => '/login/customer',
            'web_url' => route('portal.login'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function staffRole(string $base): array
    {
        return [
            'id' => 'staff',
            'label' => 'Admin / staff',
            'description' => 'ISP operations, billing desk, subscribers, and network',
            'enabled' => true,
            'mode' => 'native',
            'web_path' => '/admin/login',
            'web_url' => MobileAppLinks::staffLoginUrl(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function resellerRole(string $base): ?array
    {
        if (! config('reseller_portal.enabled', true)) {
            return null;
        }

        return [
            'id' => 'reseller',
            'label' => 'Reseller / partner',
            'description' => 'Collections, due reports, and partner dashboard',
            'enabled' => true,
            'mode' => 'web',
            'web_path' => '/reseller/login',
            'web_url' => $base.'/reseller/login',
        ];
    }
}
