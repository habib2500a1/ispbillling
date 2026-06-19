<?php

namespace App\Services\Olt;

use App\Models\Device;
use App\Models\OltHealthLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

final class OltTrafficHistoryService
{
    public const PERIODS = [
        '1h' => 1,
        '24h' => 24,
        '7d' => 168,
        '30d' => 720,
    ];

    /**
     * @return array{
     *     labels: list<string>,
     *     download_mbps: list<float|null>,
     *     upload_mbps: list<float|null>,
     *     peak_download_mbps: float,
     *     peak_upload_mbps: float,
     *     current_download_mbps: ?float,
     *     current_upload_mbps: ?float
     * }
     */
    public function series(int $oltId, string $period = '24h'): array
    {
        $empty = [
            'labels' => [],
            'download_mbps' => [],
            'upload_mbps' => [],
            'peak_download_mbps' => 0.0,
            'peak_upload_mbps' => 0.0,
            'current_download_mbps' => null,
            'current_upload_mbps' => null,
        ];

        if (! Schema::hasTable('olt_health_logs')) {
            return $this->fromDeviceMeta($oltId, $empty);
        }

        $hours = self::PERIODS[$period] ?? 24;
        $logs = OltHealthLog::query()
            ->where('device_id', $oltId)
            ->where('sampled_at', '>=', now()->subHours($hours))
            ->whereNotNull('metrics')
            ->orderBy('sampled_at')
            ->get(['metrics', 'sampled_at']);

        if ($logs->isEmpty()) {
            return $this->fromDeviceMeta($oltId, $empty);
        }

        $labels = [];
        $download = [];
        $upload = [];

        foreach ($logs as $log) {
            $m = is_array($log->metrics) ? $log->metrics : [];
            $dl = isset($m['download_mbps']) ? (float) $m['download_mbps'] : null;
            $ul = isset($m['upload_mbps']) ? (float) $m['upload_mbps'] : null;
            if ($dl === null && $ul === null) {
                continue;
            }
            $labels[] = $log->sampled_at->format($hours <= 24 ? 'H:i' : 'M-d H:i');
            $download[] = $dl;
            $upload[] = $ul;
        }

        return [
            'labels' => $labels,
            'download_mbps' => $download,
            'upload_mbps' => $upload,
            'peak_download_mbps' => (float) max($download ?: [0]),
            'peak_upload_mbps' => (float) max($upload ?: [0]),
            'current_download_mbps' => $download !== [] ? end($download) : null,
            'current_upload_mbps' => $upload !== [] ? end($upload) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $empty
     * @return array<string, mixed>
     */
    private function fromDeviceMeta(int $oltId, array $empty): array
    {
        $olt = Device::query()->find($oltId);
        $meta = is_array($olt?->meta) ? $olt->meta : [];

        $empty['current_download_mbps'] = isset($meta['traffic_download_mbps']) ? (float) $meta['traffic_download_mbps'] : null;
        $empty['current_upload_mbps'] = isset($meta['traffic_upload_mbps']) ? (float) $meta['traffic_upload_mbps'] : null;

        return $empty;
    }
}
