<?php

namespace App\Services\Automation;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SchedulerStatus
{
    public const HEARTBEAT_KEY = 'isp:scheduler:last-heartbeat';

    public function touchHeartbeat(): void
    {
        Cache::forever(self::HEARTBEAT_KEY, now()->toIso8601String());
    }

    public function lastHeartbeat(): ?Carbon
    {
        $raw = Cache::get(self::HEARTBEAT_KEY);

        return filled($raw) ? Carbon::parse((string) $raw) : null;
    }

    public function logLastModified(): ?Carbon
    {
        $path = storage_path('logs/scheduler.log');

        if (! is_file($path)) {
            return null;
        }

        $mtime = @filemtime($path);

        return $mtime ? Carbon::createFromTimestamp($mtime) : null;
    }

    /**
     * @return array{healthy: bool, label: string, last_at: ?Carbon, source: string}
     */
    public function cronHealth(): array
    {
        $heartbeat = $this->lastHeartbeat();
        $logAt = $this->logLastModified();
        $lastAt = $heartbeat && $logAt
            ? ($heartbeat->greaterThan($logAt) ? $heartbeat : $logAt)
            : ($heartbeat ?? $logAt);

        if ($lastAt === null) {
            return [
                'healthy' => false,
                'label' => 'Not detected',
                'last_at' => null,
                'source' => 'none',
            ];
        }

        $minutesAgo = $lastAt->diffInMinutes(now());
        $healthy = $minutesAgo <= 3;

        return [
            'healthy' => $healthy,
            'label' => $healthy
                ? 'Running ('.$lastAt->diffForHumans().')'
                : 'Stale — last activity '.$lastAt->diffForHumans(),
            'last_at' => $lastAt,
            'source' => $heartbeat && ($logAt === null || $heartbeat->greaterThanOrEqualTo($logAt)) ? 'heartbeat' : 'log',
        ];
    }

    public function failedQueueJobsCount(): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return (int) DB::table('failed_jobs')->count();
    }

    public function pendingQueueJobsCount(): int
    {
        if (! Schema::hasTable('jobs')) {
            return 0;
        }

        return (int) DB::table('jobs')->count();
    }
}
