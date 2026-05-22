<?php

namespace App\Support;

final class MobileAppLinks
{
    public static function downloadUrl(): string
    {
        $local = public_path('downloads/isp-radiant.apk');
        if (self::localApkUsable($local)) {
            return self::cacheBustedLocalUrl('/downloads/isp-radiant.apk', 'mobile/isp_radiant/pubspec.yaml');
        }

        $configured = config('mobile.apk_download_url');
        if (filled($configured)) {
            return (string) $configured;
        }

        if (config('mobile.use_github_releases', true)) {
            return MobileApkGithub::radiantDownloadUrl();
        }

        return MobileApkGithub::radiantDownloadUrl();
    }

    public static function mfsVerifyDownloadUrl(): string
    {
        $local = public_path('downloads/isp-mfs-verify.apk');
        if (self::localApkUsable($local)) {
            return self::cacheBustedLocalUrl('/downloads/isp-mfs-verify.apk', MobileApkRelease::MFS_VERIFY_PUBSPEC);
        }

        $configured = config('mobile.mfs_verify_apk_url');
        if (filled($configured)) {
            return (string) $configured;
        }

        if (config('mobile.use_github_releases', true)) {
            return MobileApkGithub::mfsVerifyDownloadUrl();
        }

        return MobileApkGithub::mfsVerifyDownloadUrl();
    }

    /**
     * @return 'server'|'github'|'configured'
     */
    public static function mfsVerifySource(): string
    {
        if (self::localApkUsable(public_path('downloads/isp-mfs-verify.apk'))) {
            return 'server';
        }

        if (filled(config('mobile.mfs_verify_apk_url'))) {
            return 'configured';
        }

        return 'github';
    }

    /**
     * Direct update URL (version query busts CDN/browser cache).
     */
    public static function mfsVerifyUpdateUrl(): string
    {
        return MobileApkRelease::mfsVerify()['update_url'];
    }

    /**
     * @return array{unified: string, mfs_verify: string, mfs_verify_update: string, mfs_verify_version: string, unified_label: string, mfs_verify_label: string, mfs_verify_source: string}
     */
    public static function downloadCards(): array
    {
        $mfs = MobileApkRelease::mfsVerify();

        return [
            'unified' => self::downloadUrl(),
            'mfs_verify' => $mfs['download_url'],
            'mfs_verify_update' => $mfs['update_url'],
            'mfs_verify_version' => $mfs['version_label'],
            'mfs_verify_source' => self::mfsVerifySource(),
            'unified_label' => 'Radiant ISP (Admin + Client)',
            'mfs_verify_label' => 'RCL SMS (payment phone) v'.$mfs['version_label'],
        ];
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

    private static function localApkUsable(string $absolutePath): bool
    {
        return is_file($absolutePath) && filesize($absolutePath) > 1000;
    }

    private static function cacheBustedLocalUrl(string $publicPath, string $pubspecRelative): string
    {
        $url = url($publicPath);
        $meta = MobileApkRelease::parsePubspecVersion($pubspecRelative);
        if ($meta === null) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'v='.$meta['version'].'_'.$meta['build'];
    }
}
