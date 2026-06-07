<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HorizonQueueMonitor extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static string $view = 'filament.pages.horizon-queue-monitor';

    protected static ?string $navigationLabel = 'Queue monitor';

    protected static ?string $title = 'Queue monitor';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $slug = 'queue-monitor';

    protected static ?int $navigationSort = 73;

    public static function canAccess(): bool
    {
        return Gate::allows('viewHorizon');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getHorizonUrl(): string
    {
        $path = trim((string) config('horizon.path', 'horizon'), '/');

        return url($path === '' ? '/horizon' : '/'.$path);
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $queueConnection = (string) config('queue.default', 'sync');

        return [
            'horizon_running' => $this->isHorizonRunning(),
            'queue_connection' => $queueConnection,
            'heavy_jobs_enabled' => (bool) config('queue_ops.heavy_jobs_enabled', false),
            'pending_jobs' => $this->pendingJobCount($queueConnection),
            'failed_jobs' => $this->failedJobCount(),
            'horizon_url' => $this->getHorizonUrl(),
        ];
    }

    private function isHorizonRunning(): bool
    {
        try {
            return Artisan::call('horizon:status') === 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function pendingJobCount(string $queueConnection): int
    {
        try {
            if ($queueConnection === 'redis') {
                $queue = (string) config('queue.connections.redis.queue', 'default');
                $redisConnection = (string) config('queue.connections.redis.connection', 'default');

                return (int) Redis::connection($redisConnection)->llen('queues:'.$queue);
            }

            if ($queueConnection === 'database') {
                $table = (string) config('queue.connections.database.table', 'jobs');

                return (int) DB::table($table)->count();
            }
        } catch (\Throwable) {
            return 0;
        }

        return 0;
    }

    private function failedJobCount(): int
    {
        try {
            return (int) DB::table(config('queue.failed.table', 'failed_jobs'))->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
