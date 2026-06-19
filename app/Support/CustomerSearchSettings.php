<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Schema;

/**
 * Customer search (Scout + Meilisearch) — dashboard-managed, minimal .env.
 * Master key auto-derived from APP_KEY when not overridden in app_settings.
 */
final class CustomerSearchSettings
{
    public const MASTER_KEY_SALT = 'isp-customer-search-meili-v1';

    public static function enabled(): bool
    {
        return (bool) config('customer_search.enabled', true);
    }

    public static function useScout(): bool
    {
        return self::enabled() && (bool) config('customer_search.use_scout', true);
    }

    public static function sqlFallback(): bool
    {
        return (bool) config('customer_search.sql_fallback', true);
    }

    public static function host(): string
    {
        $override = trim((string) config('customer_search.meilisearch_host', ''));

        return $override !== '' ? rtrim($override, '/') : self::detectDefaultHost();
    }

    public static function masterKey(): string
    {
        $override = trim((string) config('customer_search.meilisearch_key', ''));
        if ($override !== '') {
            return $override;
        }

        $envOverride = trim((string) env('MEILISEARCH_KEY', ''));
        if ($envOverride !== '') {
            return $envOverride;
        }

        $appKey = (string) config('app.key', '');
        if ($appKey !== '') {
            return hash('sha256', $appKey.'|'.self::MASTER_KEY_SALT);
        }

        return (string) config('customer_search.fallback_master_key', 'isp_meili_docker_internal_v1');
    }

    public static function detectDefaultHost(): string
    {
        if (self::runningInDockerStack()) {
            return 'http://meilisearch:7700';
        }

        return 'http://127.0.0.1:7700';
    }

    public static function runningInDockerStack(): bool
    {
        if (file_exists('/.dockerenv')) {
            return true;
        }

        return in_array((string) env('DB_HOST', ''), ['postgres', 'pgsql', 'db'], true);
    }

    public static function indexBootstrapped(): bool
    {
        if (! Schema::hasTable('app_settings')) {
            return false;
        }

        return AppSetting::query()
            ->where('key', 'customer_search.index_bootstrapped')
            ->where('value', '1')
            ->exists();
    }

    public static function markIndexBootstrapped(): void
    {
        AppSetting::putValue('customer_search.index_bootstrapped', '1');
    }

    /**
     * @return array<string, mixed>
     */
    public static function dashboardSnapshot(): array
    {
        return [
            'enabled' => self::enabled(),
            'host' => self::host(),
            'key_source' => self::keySourceLabel(),
            'use_scout' => self::useScout(),
            'sql_fallback' => self::sqlFallback(),
            'index_bootstrapped' => self::indexBootstrapped(),
            'master_key_preview' => substr(self::masterKey(), 0, 8).'…',
        ];
    }

    private static function keySourceLabel(): string
    {
        if (trim((string) config('customer_search.meilisearch_key', '')) !== '') {
            return 'Dashboard override';
        }

        if (trim((string) env('MEILISEARCH_KEY', '')) !== '') {
            return '.env override';
        }

        if ((string) config('app.key', '') !== '') {
            return 'Auto (APP_KEY)';
        }

        return 'Built-in Docker default';
    }
}
