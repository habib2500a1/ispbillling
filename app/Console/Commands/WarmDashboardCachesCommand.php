<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Dashboard\DashboardMetricsService;
use App\Support\DeployReady;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class WarmDashboardCachesCommand extends Command
{
    protected $signature = 'isp:warm-dashboard-caches {--tenant= : Tenant id (default: all active tenants)}';

    protected $description = 'Pre-build NOC wall and dashboard caches after deploy so first page load is fast';

    public function handle(DashboardMetricsService $metrics): int
    {
        if (! Schema::hasTable('tenants')) {
            $metrics->warmCaches(1);
            $this->info('Warmed dashboard caches for tenant 1.');

            return self::SUCCESS;
        }

        $tenantId = $this->option('tenant');
        $tenantIds = $tenantId !== null
            ? [(int) $tenantId]
            : Tenant::query()->where('is_active', true)->pluck('id')->all();

        if ($tenantIds === []) {
            $tenantIds = [1];
        }

        foreach ($tenantIds as $id) {
            $metrics->warmCaches((int) $id);
            $this->line('Warmed dashboard caches for tenant '.(int) $id);
        }

        DeployReady::markReady();
        $this->info('Dashboard caches ready.');

        return self::SUCCESS;
    }
}
