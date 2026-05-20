<?php

namespace App\Support;

final class MobileAppLinks
{
    public static function downloadUrl(): string
    {
        $configured = config('mobile.apk_download_url');

        if (filled($configured)) {
            return (string) $configured;
        }

        return url('/downloads/isp-radiant.apk');
    }

    public static function portalLoginUrl(): ?string
    {
        if (! config('portal.enabled', true)) {
            return null;
        }

        return route('portal.login');
    }

    public static function staffLoginUrl(): string
    {
        return url('/admin/login');
    }

    public static function landingUrl(): string
    {
        $landingHost = config('domains.landing');

        if (filled($landingHost)) {
            $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

            return $scheme.'://'.$landingHost.'/';
        }

        return url('/');
    }
}
