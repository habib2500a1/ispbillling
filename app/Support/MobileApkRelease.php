<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Published APK version + cache-busted direct update URL (from pubspec or build manifest).
 */
final class MobileApkRelease
{
    public const MFS_VERIFY_PUBSPEC = 'mobile/mfs_verify/pubspec.yaml';

    public const MFS_VERIFY_MANIFEST = 'downloads/mfs-verify-version.json';

    /**
     * @return array{
     *     app: string,
     *     name: string,
     *     version: string,
     *     build: int,
     *     version_label: string,
     *     apk_path: string,
     *     download_url: string,
     *     update_url: string,
     *     file_exists: bool,
     *     file_size_mb: float|null,
     *     updated_at: string|null,
     * }
     */
    public static function mfsVerify(): array
    {
        $manifest = self::readManifest(self::MFS_VERIFY_MANIFEST);
        $fromPubspec = self::parsePubspec(self::MFS_VERIFY_PUBSPEC);

        $version = (string) ($manifest['version'] ?? $fromPubspec['version'] ?? config('mobile.mfs_verify_version', '1.0.0'));
        $build = (int) ($manifest['build'] ?? $fromPubspec['build'] ?? config('mobile.mfs_verify_build', 1));
        $name = (string) ($manifest['name'] ?? 'RCL SMS');

        $baseUrl = MobileAppLinks::mfsVerifyDownloadUrl();
        $apkPath = public_path('downloads/isp-mfs-verify.apk');
        $fileExists = is_file($apkPath); // optional local copy; production uses GitHub Releases
        $fileSizeMb = $fileExists ? round(filesize($apkPath) / 1024 / 1024, 1) : null;
        $updatedAt = $fileExists ? date('Y-m-d H:i', filemtime($apkPath)) : null;

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return [
            'app' => 'mfs_verify',
            'name' => $name,
            'version' => $version,
            'build' => $build,
            'version_label' => $version.'+'.$build,
            'apk_path' => '/downloads/isp-mfs-verify.apk',
            'download_url' => $baseUrl,
            'update_url' => $baseUrl.$separator.'v='.$version.'_'.$build,
            'file_exists' => $fileExists,
            'file_size_mb' => $fileSizeMb,
            'updated_at' => $updatedAt,
        ];
    }

    /**
     * @return array{version: string, build: int}|null
     */
    public static function parsePubspecVersion(string $relativePath): ?array
    {
        $parsed = self::parsePubspec($relativePath);

        return $parsed === [] ? null : $parsed;
    }

    /**
     * @return array{version: string, build: int}|array{}
     */
    private static function parsePubspec(string $relativePath): array
    {
        $path = base_path($relativePath);
        if (! is_file($path)) {
            return [];
        }

        $content = File::get($path);
        if (! preg_match('/^version:\s*([^\s#]+)/m', $content, $m)) {
            return [];
        }

        $raw = trim($m[1]);
        if (preg_match('/^([\d.]+)\+(\d+)$/', $raw, $parts)) {
            return ['version' => $parts[1], 'build' => (int) $parts[2]];
        }

        return ['version' => $raw, 'build' => 1];
    }

    /**
     * @return array<string, mixed>
     */
    private static function readManifest(string $relativePublicPath): array
    {
        $path = public_path($relativePublicPath);
        if (! is_file($path)) {
            return [];
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  array{version: string, build: int, name?: string}  $meta
     */
    public static function writeMfsVerifyManifest(array $meta): void
    {
        $path = public_path(self::MFS_VERIFY_MANIFEST);
        File::ensureDirectoryExists(dirname($path));

        $payload = [
            'app' => 'mfs_verify',
            'name' => $meta['name'] ?? 'MFS SMS Verify',
            'version' => $meta['version'],
            'build' => $meta['build'],
            'download_url' => MobileApkGithub::mfsVerifyDownloadUrl(),
            'github_tag' => MobileApkGithub::mfsVerifyTag(),
            'published_at' => now()->toIso8601String(),
        ];

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }
}
