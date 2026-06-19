<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardMetricsService;
use App\Support\TenantResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NocWallStreamController extends Controller
{
    public function __invoke(Request $request, DashboardMetricsService $metrics): StreamedResponse
    {
        $tenantId = TenantResolver::requiredTenantId();

        return response()->stream(function () use ($metrics, $tenantId): void {
            $maxIterations = max(1, min(12, (int) config('dashboard.noc_stream_max_iterations', 6)));
            $iterations = 0;

            while ($iterations < $maxIterations) {
                if (connection_aborted()) {
                    break;
                }

                $wall = $metrics->nocWallPayload($tenantId);
                $noc = $wall['noc'] ?? [];

                $payload = json_encode([
                    'at' => now()->toIso8601String(),
                    'kpis' => [
                        'online_now' => $noc['online_now'] ?? 0,
                        'user_down' => $noc['user_down'] ?? 0,
                        'wan_download_mbps' => $noc['wan_download_mbps'] ?? 0,
                        'wan_upload_mbps' => $noc['wan_upload_mbps'] ?? 0,
                        'users_download_mbps' => $noc['users_download_mbps'] ?? 0,
                        'users_upload_mbps' => $noc['users_upload_mbps'] ?? 0,
                        'link_down' => $noc['link_down'] ?? 0,
                        'olt_offline' => $noc['olt_offline'] ?? 0,
                        'olt_partial' => $noc['olt_partial'] ?? 0,
                        'fiber_alerts' => $noc['fiber_alerts'] ?? 0,
                        'active_outages' => $noc['active_outages']['count'] ?? 0,
                    ],
                    'gpon' => $wall['gpon'] ?? [],
                    'support' => $wall['support'] ?? [],
                ], JSON_THROW_ON_ERROR);

                echo "event: noc\n";
                echo 'data: '.$payload."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                sleep((int) config('dashboard.noc_stream_interval_seconds', 30));
                $iterations++;
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
