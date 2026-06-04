<?php

namespace App\Services\Optical;

use App\Models\Device;
use App\Models\OnuSignalLog;
use App\Models\PonSignalStat;
use App\Services\Olt\OltPortCatalogService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class OpticalSignalHistoryService
{
    public function __construct(
        private readonly OltPortCatalogService $portCatalog,
    ) {}
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
        $bucketExpr = $this->hourBucketExpression();

        $rows = OnuSignalLog::query()
            ->where('tenant_id', $tenantId)
            ->where('sampled_at', '>=', $since)
            ->whereNotNull('rx_power_dbm')
            ->selectRaw("{$bucketExpr} as bucket")
            ->selectRaw('AVG(rx_power_dbm) as avg_rx')
            ->selectRaw('SUM(CASE WHEN rx_power_dbm < ? THEN 1 ELSE 0 END) as weak_count', [$warn])
            ->groupByRaw('bucket')
            ->orderByRaw('bucket')
            ->get();

        $labels = [];
        $avgRx = [];
        $weak = [];

        foreach ($rows as $row) {
            $labels[] = Carbon::parse((string) $row->bucket)->format('M j H:i');
            $avgRx[] = $row->avg_rx !== null ? round((float) $row->avg_rx, 2) : null;
            $weak[] = (int) $row->weak_count;
        }

        return ['labels' => $labels, 'avg_rx' => $avgRx, 'weak_count' => $weak];
    }

    private function hourBucketExpression(): string
    {
        return match (OnuSignalLog::query()->getConnection()->getDriverName()) {
            'pgsql' => "date_trunc('hour', sampled_at)",
            'sqlite' => "strftime('%Y-%m-%d %H:00:00', sampled_at)",
            default => "DATE_FORMAT(sampled_at, '%Y-%m-%d %H:00:00')",
        };
    }

    /**
     * Latest snapshot per OLT PON (card/pon), not duplicate poll history rows.
     *
     * @return Collection<int, PonSignalStat>
     */
    public function ponPortStats(int $tenantId, int $limit = 0): Collection
    {
        $this->portCatalog->ensureForTenant($tenantId);

        if (config('optical.refresh_pon_stats_on_noc_view', true)) {
            $cacheKey = "pon_stats_refresh:{$tenantId}";
            if (! Cache::has($cacheKey)) {
                $health = app(PonPortHealthService::class);
                Device::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('type', 'olt')
                    ->where('status', '!=', 'decommissioned')
                    ->each(fn (Device $olt) => $health->aggregateForOlt($olt, now()));
                Cache::put($cacheKey, true, now()->addMinutes(2));
            }
        }

        $stats = PonSignalStat::query()
            ->where('pon_signal_stats.tenant_id', $tenantId)
            ->latestPerPort($tenantId)
            ->with([
                'olt:id,display_name,serial_number',
                'oltPort:id,device_id,card_index,pon_index,label,meta',
            ])
            ->get()
            ->sortBy(fn (PonSignalStat $s): string => sprintf(
                '%s|%03d|%03d',
                $s->olt?->display_name ?? '',
                (int) ($s->card_no ?? 0),
                (int) ($s->pon_no ?? 0),
            ))
            ->values();

        if ($limit > 0) {
            return $stats->take($limit);
        }

        return $stats;
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
