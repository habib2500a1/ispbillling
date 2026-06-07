<?php

namespace App\Support;

final class AppInstalled
{
    public static function flagPath(): string
    {
        return storage_path('framework/.app-installed');
    }

    public static function isInstalled(): bool
    {
        if (is_file(self::flagPath())) {
            return true;
        }

        if (app()->environment('local', 'testing')) {
            return true;
        }

        return self::legacyDatabaseReady();
    }

    public static function markInstalled(): void
    {
        $path = self::flagPath();
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($path, (string) now()->toIso8601String());
        DeployReady::markReady();
    }

    public static function bypassPaths(): array
    {
        return [
            'install',
            'install/*',
            'up',
            'health',
            'health/*',
        ];
    }

    private static function legacyDatabaseReady(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable('migrations')
                && \Illuminate\Support\Facades\Schema::hasTable('users')
                && \App\Models\User::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
