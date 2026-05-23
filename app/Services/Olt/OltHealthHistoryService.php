<?php

namespace App\Services\Olt;

use App\Models\Device;
use App\Models\OltHealthLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

final class OltHealthHistoryService
{
    public const PERIODS = [
        '1h' => 1,
        '24h' => 24,
        '7d' => 168,
        '30d' => 720,
    ];

    /**
     * @return array{labels: list<string>, cpu: list<int|null>, memory: list<int|null>, temperature: list<float|null>, health_score: list<int|null>}
     */
    public function series(int $oltId, string $period = '24h'): array
    {
        $empty = [
            'labels' => [],
            'cpu' => [],
            'memory' => [],
            'temperature' => [],
            'health_score' => [],
        ];

        if (! Schema::hasTable('olt_health_logs')) {
            return $empty;
        }

        $hours = self::PERIODS[$period] ?? 24;
        $since = now()->subHours($hours);
        $bucketMinutes = match ($period) {
            '1h' => 5,
            '24h' => 30,
            '7d' => 360,
            '30d' => 1440,
            default => 30,
        };

        $logs = OltHealthLog::query()
            ->where('device_id', $oltId)
            ->where('sampled_at', '>=', $since)
            ->orderBy('sampled_at')
            ->get(['cpu_percent', 'memory_percent', 'temperature_c', 'health_score', 'sampled_at']);

        if ($logs->isEmpty()) {
            return $this->seriesFromOltHealthJson($oltId);
        }

        $buckets = [];
        $bucketSeconds = $bucketMinutes * 60;
        foreach ($logs as $log) {
            $epoch = $log->sampled_at->timestamp;
            $bucketEpoch = (int) (floor($epoch / $bucketSeconds) * $bucketSeconds);
            $key = date('Y-m-d H:i', $bucketEpoch);
            $buckets[$key] = $log;
        }

        $labels = [];
        $cpu = [];
        $memory = [];
        $temperature = [];
        $health_score = [];

        foreach ($buckets as $key => $log) {
            $labels[] = Carbon::parse($key)->format($hours <= 24 ? 'H:i' : 'M-d H:i');
            $cpu[] = $log->cpu_percent;
            $memory[] = $log->memory_percent;
            $temperature[] = $log->temperature_c !== null ? (float) $log->temperature_c : null;
            $health_score[] = $log->health_score;
        }

        return compact('labels', 'cpu', 'memory', 'temperature', 'health_score');
    }

    /**
     * @return array{labels: list<string>, cpu: list<int|null>, memory: list<int|null>, temperature: list<float|null>, health_score: list<int|null>}
     */
    private function seriesFromOltHealthJson(int $oltId): array
    {
        $olt = Device::query()->find($oltId);
        $health = is_array($olt?->olt_health) ? $olt->olt_health : [];

        return [
            'labels' => [now()->format('H:i')],
            'cpu' => [isset($health['cpu_percent']) ? (int) $health['cpu_percent'] : null],
            'memory' => [isset($health['memory_percent']) ? (int) $health['memory_percent'] : null],
            'temperature' => [isset($health['temperature_c']) ? (float) $health['temperature_c'] : null],
            'health_score' => [isset($health['health_score']) ? (int) $health['health_score'] : null],
        ];
    }
}
