<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

final class MobileApkBuildInfo
{
    public static function path(): string
    {
        return public_path('downloads/apk-build-info.json');
    }

    /**
     * @return array{app_url: string, api_base_url: string, built_at: string, radiant_version?: string, mfs_version?: string}|null
     */
    public static function read(): ?array
    {
        $path = self::path();
        if (! is_file($path)) {
            return null;
        }

        try {
            $data = json_decode((string) File::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($data) || empty($data['app_url'])) {
            return null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function write(string $appUrl, string $apiBaseUrl, array $extra = []): void
    {
        $dir = dirname(self::path());
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $payload = array_merge([
            'app_url' => rtrim($appUrl, '/'),
            'api_base_url' => rtrim($apiBaseUrl, '/'),
            'built_at' => now()->toIso8601String(),
        ], $extra);

        File::put(self::path(), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    public static function domainMatchesAppUrl(): bool
    {
        $info = self::read();
        if ($info === null) {
            return false;
        }

        $builtHost = parse_url((string) $info['app_url'], PHP_URL_HOST);
        $currentHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($builtHost) || ! is_string($currentHost)) {
            return false;
        }

        return strtolower($builtHost) === strtolower($currentHost);
    }

    public static function statusLabel(): string
    {
        $info = self::read();
        if ($info === null) {
            return 'unknown';
        }

        return self::domainMatchesAppUrl() ? 'ok' : 'domain_mismatch';
    }
}
