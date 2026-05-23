<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardMetricsService;
use App\Support\TenantResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardStreamController extends Controller
{
    public function __invoke(Request $request, DashboardMetricsService $metrics): StreamedResponse
    {
        $tenantId = TenantResolver::requiredTenantId();

        return response()->stream(function () use ($metrics, $tenantId): void {
            // Keep SSE short so PHP-FPM workers are not held for hours (pool max_children is small).
            $maxIterations = max(1, min(12, (int) config('dashboard.stream_max_iterations', 6)));
            $iterations = 0;
            while ($iterations < $maxIterations) {
                if (connection_aborted()) {
                    break;
                }

                $payload = json_encode([
                    'at' => now()->toIso8601String(),
                    'snapshot' => $metrics->snapshot($tenantId),
                    'support' => $metrics->supportSnapshot($tenantId),
                ], JSON_THROW_ON_ERROR);

                echo "event: metrics\n";
                echo 'data: '.$payload."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                sleep((int) config('dashboard.stream_interval_seconds', 60));
                $iterations++;
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
