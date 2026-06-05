<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

final class EnsureStorageWritable
{
    /** @return list<string> */
    public static function directories(): array
    {
        return [
            storage_path('app'),
            storage_path('app/livewire-tmp'),
            storage_path('app/private/livewire-tmp'),
            storage_path('app/private/olt-vpn-uploads'),
            storage_path('app/private/olt-vpn'),
            storage_path('app/private/olt-pptp'),
            storage_path('app/public'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];
    }

    /**
     * @return list<string> Human-readable problems.
     */
    public static function findIssues(): array
    {
        $issues = [];

        foreach (self::directories() as $dir) {
            if (! is_dir($dir)) {
                $issues[] = "Missing directory: {$dir}";

                continue;
            }

            if (! is_writable($dir)) {
                $owner = function_exists('posix_getpwuid') && function_exists('fileowner')
                    ? (posix_getpwuid(fileowner($dir))['name'] ?? (string) fileowner($dir))
                    : (string) fileowner($dir);
                $issues[] = "Not writable: {$dir} (owner: {$owner})";
            }
        }

        return $issues;
    }

    /**
     * Fix ownership when run as root (deploy). Returns false if not root or fix failed.
     */
    public static function fixOwnership(string $user = 'www-data', string $group = 'www-data'): bool
    {
        if (! function_exists('posix_geteuid') || posix_geteuid() !== 0) {
            return false;
        }

        foreach (self::directories() as $dir) {
            if (! is_dir($dir)) {
                File::ensureDirectoryExists($dir, 0775);
            }

            self::chownTree($dir, $user, $group);
        }

        return self::findIssues() === [];
    }

    /**
     * Recursive chown — required after `php artisan` run as root (compiled views).
     */
    private static function chownTree(string $path, string $user, string $group): void
    {
        if (! is_dir($path)) {
            @chown($path, $user);
            @chgrp($path, $group);

            return;
        }

        @chown($path, $user);
        @chgrp($path, $group);
        @chmod($path, 0775);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $real = $item->getPathname();
            @chown($real, $user);
            @chgrp($real, $group);
            @chmod($real, $item->isDir() ? 0775 : 0664);
        }
    }
}
