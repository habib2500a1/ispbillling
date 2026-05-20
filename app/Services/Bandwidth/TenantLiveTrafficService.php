<?php

namespace App\Services\Bandwidth;

use Illuminate\Support\Facades\Cache;

final class TenantLiveTrafficService
{
    private const CHART_PREFIX = 'tenant_live_chart:';

    /**
     * Append one per-second point for all-subscriber throughput (mobile / API polling).
     *
     * @return array{
     *   chart: array{labels: list<string>, download_mbps: list<float>, upload_mbps: list<float>},
     *   download_bps: int,
     *   upload_bps: int
     * }
     */
    public function tick(int $tenantId): array
    {
        $live = BandwidthCollectionService::currentTenantLiveBps($tenantId);
        $downBps = (int) ($live['down_bps'] ?? 0);
        $upBps = (int) ($live['up_bps'] ?? 0);

        $chart = Cache::get(self::CHART_PREFIX.$tenantId, [
            'labels' => [],
            'download_mbps' => [],
            'upload_mbps' => [],
        ]);

        $chart['labels'][] = now()->format('H:i:s');
        $chart['download_mbps'][] = round($downBps / 1_000_000, 4);
        $chart['upload_mbps'][] = round($upBps / 1_000_000, 4);

        $maxPoints = max(30, (int) config('bandwidth.live_chart_points', 120));
        foreach (['labels', 'download_mbps', 'upload_mbps'] as $key) {
            if (count($chart[$key]) > $maxPoints) {
                $chart[$key] = array_values(array_slice($chart[$key], -$maxPoints));
            }
        }

        Cache::put(self::CHART_PREFIX.$tenantId, $chart, now()->addMinutes(15));

        return [
            'chart' => $chart,
            'download_bps' => $downBps,
            'upload_bps' => $upBps,
        ];
    }

    /**
     * Historical per-second chart from bandwidth samples (fallback when cache empty).
     *
     * @return array{labels: list<string>, download_mbps: list<float>, upload_mbps: list<float>}
     */
    public function chartFromSamples(int $tenantId, int $minutes = 2, int $maxPoints = 120): array
    {
        return BandwidthCollectionService::aggregateLiveMbpsPerSecond($tenantId, $minutes, $maxPoints);
    }
}
