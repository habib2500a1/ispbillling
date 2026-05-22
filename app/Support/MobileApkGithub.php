<?php

namespace App\Support;

/**
 * APK binaries live on GitHub Releases — not in git or public/downloads.
 */
final class MobileApkGithub
{
    public const RADIANT_ASSET = 'isp-radiant.apk';

    public const MFS_VERIFY_ASSET = 'isp-mfs-verify.apk';

    public static function repo(): string
    {
        return (string) config('mobile.github_repo', 'habib2500a1/ispbillling');
    }

    public static function radiantTag(): string
    {
        $tag = (string) config('mobile.radiant_github_tag', '');

        if ($tag !== '') {
            return $tag;
        }

        $fromPubspec = MobileApkRelease::parsePubspecVersion('mobile/isp_radiant/pubspec.yaml');

        return $fromPubspec !== null
            ? 'isp-radiant-v'.$fromPubspec['version']
            : 'isp-radiant-latest';
    }

    public static function mfsVerifyTag(): string
    {
        $tag = (string) config('mobile.mfs_github_tag', '');

        if ($tag !== '') {
            return $tag;
        }

        $fromPubspec = MobileApkRelease::parsePubspecVersion(MobileApkRelease::MFS_VERIFY_PUBSPEC);

        return $fromPubspec !== null
            ? 'mfs-verify-v'.$fromPubspec['version']
            : 'mfs-verify-latest';
    }

    public static function assetUrl(string $tag, string $assetName): string
    {
        $repo = self::repo();

        return "https://github.com/{$repo}/releases/download/{$tag}/{$assetName}";
    }

    public static function radiantDownloadUrl(): string
    {
        return self::assetUrl(self::radiantTag(), self::RADIANT_ASSET);
    }

    public static function mfsVerifyDownloadUrl(): string
    {
        $url = self::assetUrl(self::mfsVerifyTag(), self::MFS_VERIFY_ASSET);
        $meta = MobileApkRelease::parsePubspecVersion(MobileApkRelease::MFS_VERIFY_PUBSPEC);
        if ($meta === null) {
            return $url;
        }

        return $url.'?v='.$meta['version'].'_'.$meta['build'];
    }
}
