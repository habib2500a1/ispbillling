<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

final class DeployReady
{
    public static function flagPath(): string
    {
        return storage_path('framework/deploy-ready');
    }

    public static function bootstrappingPath(): string
    {
        return storage_path('framework/deploy-bootstrapping');
    }

    public static function isReady(): bool
    {
        if (app()->runningInConsole()) {
            return true;
        }

        if (app()->environment('local', 'testing')) {
            return true;
        }

        if (is_file(self::bootstrappingPath())) {
            return false;
        }

        if (is_file(self::flagPath())) {
            return true;
        }

        return self::legacyProductionReady();
    }

    public static function markBootstrapping(): void
    {
        self::clearReady();
        self::writeFlag(self::bootstrappingPath(), 'bootstrapping');
    }

    public static function markReady(): void
    {
        if (is_file(self::bootstrappingPath())) {
            @unlink(self::bootstrappingPath());
        }

        self::writeFlag(self::flagPath(), (string) now()->toIso8601String());
    }

    public static function clearReady(): void
    {
        if (is_file(self::flagPath())) {
            @unlink(self::flagPath());
        }
    }

    private static function legacyProductionReady(): bool
    {
        try {
            return Schema::hasTable('migrations') && Schema::hasTable('users');
        } catch (\Throwable) {
            return false;
        }
    }

    private static function writeFlag(string $path, string $contents): void
    {
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($path, $contents);
    }
}
