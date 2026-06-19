<?php

namespace App\Console\Commands;

use App\Events\NocWallUpdated;
use App\Models\Tenant;
use App\Services\Dashboard\DashboardMetricsService;
use Illuminate\Console\Command;

class BroadcastNocWallCommand extends Command
{
    protected $signature = 'isp:broadcast-noc-wall';

    protected $description = 'Broadcast live NOC wall KPIs to connected admin clients';

    public function handle(DashboardMetricsService $metrics): int
    {
        if (in_array(config('broadcasting.default'), ['log', 'null'], true)) {
            return self::SUCCESS;
        }

        Tenant::query()->where('is_active', true)->each(function (Tenant $tenant) use ($metrics): void {
            $wall = $metrics->nocWallPayload($tenant->id);
            $noc = $wall['noc'] ?? [];

            event(new NocWallUpdated($tenant->id, [
                'at' => now()->toIso8601String(),
                'kpis' => [
                    'online_now' => $noc['online_now'] ?? 0,
                    'user_down' => $noc['user_down'] ?? 0,
                    'wan_download_mbps' => $noc['wan_download_mbps'] ?? 0,
                    'wan_upload_mbps' => $noc['wan_upload_mbps'] ?? 0,
                    'link_down' => $noc['link_down'] ?? 0,
                    'olt_offline' => $noc['olt_offline'] ?? 0,
                    'fiber_alerts' => $noc['fiber_alerts'] ?? 0,
                    'active_outages' => $noc['active_outages']['count'] ?? 0,
                ],
                'alerts' => $wall['alerts'] ?? [],
            ]));
        });

        return self::SUCCESS;
    }
}
