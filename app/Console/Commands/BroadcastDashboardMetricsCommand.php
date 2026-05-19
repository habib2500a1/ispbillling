<?php

namespace App\Console\Commands;

use App\Events\DashboardMetricsUpdated;
use App\Models\Tenant;
use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Console\Command;

class BroadcastDashboardMetricsCommand extends Command
{
    protected $signature = 'isp:broadcast-dashboard-metrics';

    protected $description = 'Broadcast live dashboard KPIs to connected admin clients';

    public function handle(DashboardMetricsService $metrics): int
    {
        if (config('broadcasting.default') === 'log' || config('broadcasting.default') === 'null') {
            return self::SUCCESS;
        }

        Tenant::query()->where('is_active', true)->each(function (Tenant $tenant) use ($metrics): void {
            event(new DashboardMetricsUpdated($tenant->id, [
                'snapshot' => $metrics->snapshot($tenant->id),
                'support' => $metrics->supportSnapshot($tenant->id),
            ]));
        });

        return self::SUCCESS;
    }
}
