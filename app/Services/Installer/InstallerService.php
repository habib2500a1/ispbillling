<?php

namespace App\Services\Installer;

use App\Support\AppInstalled;
use App\Support\EnvWriter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use PDO;
use Throwable;

final class InstallerService
{
    /**
     * @return array{ok: bool, php_version: string, checks: array<int, array{label: string, ok: bool, hint?: string}>}
     */
    public function requirements(): array
    {
        $checks = [];
        $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
        $checks[] = [
            'label' => 'PHP 8.2+',
            'ok' => $phpOk,
            'hint' => 'Current: '.PHP_VERSION,
        ];

        foreach (['pdo', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'fileinfo'] as $ext) {
            $checks[] = [
                'label' => "ext-{$ext}",
                'ok' => extension_loaded($ext),
            ];
        }

        $checks[] = [
            'label' => 'vendor/autoload.php',
            'ok' => is_file(base_path('vendor/autoload.php')),
            'hint' => 'Zip package should include vendor/. Re-run composer install if missing.',
        ];

        $checks[] = [
            'label' => '.env writable',
            'ok' => is_file(EnvWriter::path()) ? is_writable(EnvWriter::path()) : is_writable(base_path()),
        ];

        return [
            'ok' => collect($checks)->every(fn (array $c): bool => $c['ok']),
            'php_version' => PHP_VERSION,
            'checks' => $checks,
        ];
    }

    /**
     * @return array<int, array{path: string, ok: bool, writable: bool}>
     */
    public function permissionStatus(): array
    {
        $paths = [
            'storage',
            'storage/framework',
            'storage/logs',
            'bootstrap/cache',
        ];

        $rows = [];
        foreach ($paths as $path) {
            $full = base_path($path);
            if (! is_dir($full)) {
                @mkdir($full, 0775, true);
            }

            $rows[] = [
                'path' => $path,
                'ok' => is_dir($full) && is_writable($full),
                'writable' => is_writable($full),
            ];
        }

        return $rows;
    }

    public function permissionsOk(): bool
    {
        return collect($this->permissionStatus())->every(fn (array $row): bool => $row['ok']);
    }

    public function fixPermissions(): void
    {
        foreach (['storage', 'bootstrap/cache'] as $path) {
            $full = base_path($path);
            if (! is_dir($full)) {
                mkdir($full, 0775, true);
            }

            $this->chmodRecursive($full, 0775);
        }
    }

    /**
     * @param  array{driver: string, host: string, port: string, database: string, username: string, password: string}  $config
     * @return array{ok: bool, message: string}
     */
    public function testDatabase(array $config): array
    {
        $driver = $config['driver'] === 'pgsql' ? 'pgsql' : 'mysql';

        try {
            if ($driver === 'pgsql') {
                $dsn = sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s',
                    $config['host'],
                    $config['port'] ?: '5432',
                    $config['database'],
                );
            } else {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    $config['host'],
                    $config['port'] ?: '3306',
                    $config['database'],
                );
            }

            $pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_TIMEOUT => 8,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->query('SELECT 1');

            return ['ok' => true, 'message' => 'Database connection successful.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, string>  $input
     */
    public function saveDatabaseConfig(array $input): void
    {
        $driver = $input['db_driver'] === 'pgsql' ? 'pgsql' : 'mysql';

        EnvWriter::setMany([
            'DB_CONNECTION' => $driver,
            'DB_HOST' => $input['db_host'],
            'DB_PORT' => $input['db_port'] ?: ($driver === 'pgsql' ? '5432' : '3306'),
            'DB_DATABASE' => $input['db_database'],
            'DB_USERNAME' => $input['db_username'],
            'DB_PASSWORD' => $input['db_password'],
            'QUEUE_CONNECTION' => 'database',
            'CACHE_STORE' => 'database',
            'SESSION_DRIVER' => 'database',
        ]);
    }

    /**
     * @param  array<string, string>  $input
     */
    public function saveSiteConfig(array $input): void
    {
        $appUrl = rtrim($input['app_url'], '/');
        $host = parse_url($appUrl, PHP_URL_HOST) ?: $input['app_url'];
        $sessionDomain = '.'.(str_contains($host, '.') ? implode('.', array_slice(explode('.', $host), -2)) : $host);

        EnvWriter::setMany([
            'APP_NAME' => $input['app_name'],
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $appUrl,
            'ISP_BUNDLE_CSS' => 'true',
            'ISP_LANDING_DOMAIN' => $host,
            'ISP_DEFAULT_TENANT_ID' => '1',
            'INVENTORY_SHOP_TENANT_ID' => '1',
            'SESSION_DOMAIN' => $sessionDomain,
            'ISP_COMPANY_NAME' => $input['company_name'],
            'ISP_ADMIN_EMAIL' => $input['admin_email'],
            'ISP_ADMIN_PASSWORD' => $input['admin_password'],
        ]);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function runInstallation(): array
    {
        try {
            $this->fixPermissions();

            if (! $this->envHasAppKey()) {
                Artisan::call('key:generate', ['--force' => true]);
            }

            Artisan::call('config:clear');
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('isp:post-deploy', ['--fast' => true, '--no-interaction' => true]);
            Artisan::call('isp:generate-webhook-secrets', ['--write' => true, '--only-missing' => true, '--no-interaction' => true]);

            if (! is_link(public_path('storage'))) {
                Artisan::call('storage:link', ['--force' => true]);
            }

            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('event:cache');

            AppInstalled::markInstalled();

            return ['ok' => true, 'message' => 'Installation completed successfully.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function documentRootHint(): string
    {
        $public = realpath(public_path()) ?: public_path();

        return $public;
    }

    public function laravelRoot(): string
    {
        return base_path();
    }

    private function envHasAppKey(): bool
    {
        $path = EnvWriter::path();
        if (! is_file($path)) {
            return false;
        }

        $contents = File::get($path);

        return preg_match('/^APP_KEY=base64:.+/m', $contents) === 1;
    }

    private function chmodRecursive(string $path, int $mode): void
    {
        @chmod($path, $mode);

        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $this->chmodRecursive($path.DIRECTORY_SEPARATOR.$item, $mode);
        }
    }
}
