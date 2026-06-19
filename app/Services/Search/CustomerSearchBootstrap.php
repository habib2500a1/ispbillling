<?php

namespace App\Services\Search;

use App\Models\AppSetting;
use App\Support\CustomerSearchSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/** Auto-index subscribers after deploy when Meilisearch is healthy. */
final class CustomerSearchBootstrap
{
    public static function runAfterDeploy(bool $quiet = false): void
    {
        if (! Schema::hasTable('app_settings') || ! Schema::hasTable('customers')) {
            return;
        }

        CustomerSearchConfigurator::apply();

        if (! CustomerSearchSettings::enabled()) {
            return;
        }

        if (! (bool) config('customer_search.auto_index_on_deploy', true)) {
            return;
        }

        if (CustomerSearchSettings::indexBootstrapped()) {
            return;
        }

        if (! app(CustomerSearchHealthService::class)->isHealthy()) {
            if (! $quiet) {
                Log::channel('single')->info('customer_search.deploy_skip_index', [
                    'reason' => 'meilisearch_not_ready',
                ]);
            }

            return;
        }

        try {
            Artisan::call('isp:scout-sync-customers');
            CustomerSearchSettings::markIndexBootstrapped();
            AppSetting::putValue('customer_search.last_sync_at', now()->toIso8601String());

            if (! $quiet) {
                Log::channel('single')->info('customer_search.deploy_index_complete');
            }
        } catch (\Throwable $e) {
            Log::channel('single')->warning('customer_search.deploy_index_failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
