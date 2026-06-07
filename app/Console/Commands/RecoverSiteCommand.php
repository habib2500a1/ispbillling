<?php

namespace App\Console\Commands;

use App\Support\DeployReady;
use App\Support\SafeCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

final class RecoverSiteCommand extends Command
{
    protected $signature = 'isp:recover-site';

    protected $description = 'Clear stuck deploy flags, rebuild caches, and mark site ready (fixes post-deploy 500 loops)';

    public function handle(): int
    {
        if (is_file(DeployReady::bootstrappingPath())) {
            @unlink(DeployReady::bootstrappingPath());
            $this->line('Removed stuck deploy-bootstrapping flag.');
        }

        SafeCache::forget('bootstrap.app_settings_sync');
        SafeCache::forget('bootstrap.app_settings_table');
        SafeCache::forget('bootstrap.tenants_table');

        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        $this->line('Framework caches cleared.');

        if ((string) config('cache.default') === 'failover') {
            config(['cache.default' => 'redis']);
            $this->warn('Corrected cache.default failover → redis for this run.');
        }

        Artisan::call('config:cache');
        Artisan::call('route:cache');
        $this->line('Config and route cache rebuilt.');

        DeployReady::markReady();
        $this->info('Site marked deploy-ready: '.DeployReady::flagPath());

        Artisan::call('isp:warm-dashboard-caches', [], $this->output);

        return self::SUCCESS;
    }
}
