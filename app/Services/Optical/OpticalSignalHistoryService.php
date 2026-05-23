<?php

namespace App\Services\Optical;

use App\Models\Device;
use App\Models\OnuSignalLog;
use App\Models\PonSignalStat;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class OpticalSignalHistoryService
{
    /** @var array<string, int> */
    public const PERIODS = [
        '1h' => 1,
        '24h' => 24,
        '7d' => 168,
        '30d' => 720,
    ];

    /**
     * RX/TX/temp/voltage series for charts (downsampled).
     *
     * @return array{
     *   labels: list<string>,
     *   rx: list<float|null>,
     *   tx: list<float|null>,
     *   temperature: list<float|null>,
     *   voltage: list<float|null>,
     *   health: list<int|null>
     * }
     */
    public function series(int $deviceId, string $period = '24h', int $maxPoints = 120): array
    {
        $hours = self::PERIODS[$period] ?? 24;
        $since = now()->subHours($hours);

        $granularity = $hours <= 2 ? 'snapshot' : null;

        $query = OnuSignalLog::query()
            ->where('device_id', $deviceId)
            ->where('sampled_at', '>=', $since)
            ->orderBy('sampled_at');

        if ($granularity) {
            $query->where('granularity', 'snapshot');
        } else {
            $query->whereIn('granularity', ['snapshot', 'hourly']);
        }

        $logs = $query->get([
            'sampled_at', 'rx_power_dbm', 'tx_power_dbm', 'temperature_c', 'voltage_v', 'health_score',
        ]);

        if ($logs->count() > $maxPoints) {
            $logs = $this->downsample($logs, $maxPoints);
        }

        $labels = [];
        $rx = [];
        $tx = [];
        $temperature = [];
        $voltage = [];
        $health = [];

        foreach ($logs as $log) {
            $labels[] = $log->sampled_at->format($hours <= 48 ? 'M j H:i' : 'M j');
            $rx[] = $log->rx_power_dbm !== null ? (float) $log->rx_power_dbm : null;
            $tx[] = $log->tx_power_dbm !== null ? (float) $log->tx_power_dbm : null;
            $temperature[] = $log->temperature_c !== null ? (float) $log->temperature_c : null;
            $voltage[] = $log->voltage_v !== null ? (float) $log->voltage_v : null;
            $health[] = $log->health_score;
        }

        return compact('labels', 'rx', 'tx', 'temperature', 'voltage', 'health');
    }

    /**
     * Tenant-wide average RX trend for NOC dashboard.
     *
     * @return array{labels: list<string>, avg_rx: list<float|null>, weak_count: list<int>}
     */
    public function tenantAverageTrend(int $tenantId, int $hours = 24): array
    {
        $since = now()->subHours($hours);

        $warn = (float) config('optical.rx_thresholds.warning', -25);
        $logs = OnuSignalLog::query()
            ->where('tenant_id', $tenantId)
            ->where('sampled_at', '>=', $since)
            ->whereNotNull('rx_power_dbm')
            ->orderBy('sampled_at')
            ->get(['sampled_at', 'rx_power_dbm']);

        $buckets = [];
        foreach ($logs as $log) {
            $key = $log->sampled_at->format('Y-m-d H:00');
            if (! isset($buckets[$key])) {
                $buckets[$key] = ['sum' => 0.0, 'count' => 0, 'weak' => 0];
            }
            $rx = (float) $log->rx_power_dbm;
            $buckets[$key]['sum'] += $rx;
            $buckets[$key]['count']++;
            if ($rx < $warn) {
                $buckets[$key]['weak']++;
            }
        }

        $labels = [];
        $avgRx = [];
        $weak = [];

        foreach ($buckets as $key => $row) {
            $labels[] = Carbon::parse($key)->format('M j H:i');
            $avgRx[] = $row['count'] > 0 ? round($row['sum'] / $row['count'], 2) : null;
            $weak[] = $row['weak'];
        }

        return ['labels' => $labels, 'avg_rx' => $avgRx, 'weak_count' => $weak];
    }

    /**
     * @return Collection<int, PonSignalStat>
     */
    public function ponPortStats(int $tenantId, int $limit = 50): Collection
    {
        return PonSignalStat::query()
            ->where('tenant_id', $tenantId)
            ->with(['olt:id,display_name,serial_number'])
            ->orderByDesc('computed_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  Collection<int, OnuSignalLog>  $logs
     * @return Collection<int, OnuSignalLog>
     */
    private function downsample(Collection $logs, int $maxPoints): Collection
    {
        $step = max(1, (int) ceil($logs->count() / $maxPoints));
        $out = collect();
        foreach ($logs->values() as $i => $log) {
            if ($i % $step === 0) {
                $out->push($log);
            }
        }

        return $out;
    }
}
