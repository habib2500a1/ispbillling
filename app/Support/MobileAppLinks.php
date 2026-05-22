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

        if (config('mobile.use_github_releases', true)) {
            return MobileApkGithub::radiantDownloadUrl();
        }

        $local = public_path('downloads/isp-radiant.apk');

        return is_file($local) ? url('/downloads/isp-radiant.apk') : MobileApkGithub::radiantDownloadUrl();
    }

    public static function mfsVerifyDownloadUrl(): string
    {
        $configured = config('mobile.mfs_verify_apk_url');

        if (filled($configured)) {
            return (string) $configured;
        }

        if (config('mobile.use_github_releases', true)) {
            return MobileApkGithub::mfsVerifyDownloadUrl();
        }

        $local = public_path('downloads/isp-mfs-verify.apk');

        return is_file($local) ? url('/downloads/isp-mfs-verify.apk') : MobileApkGithub::mfsVerifyDownloadUrl();
    }

    /**
     * Direct update URL (version query busts CDN/browser cache).
     */
    public static function mfsVerifyUpdateUrl(): string
    {
        return MobileApkRelease::mfsVerify()['update_url'];
    }

    /**
     * @return array{unified: string, mfs_verify: string, mfs_verify_update: string, mfs_verify_version: string, unified_label: string, mfs_verify_label: string}
     */
    public static function downloadCards(): array
    {
        $mfs = MobileApkRelease::mfsVerify();

        return [
            'unified' => self::downloadUrl(),
            'mfs_verify' => $mfs['download_url'],
            'mfs_verify_update' => $mfs['update_url'],
            'mfs_verify_version' => $mfs['version_label'],
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
}
