<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AutomaticProcessResource;
use App\Models\AutomaticProcess;
use App\Models\AutomaticProcessRun;
use App\Services\Automation\SchedulerStatus;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AutomaticProcessHealthWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $failed = AutomaticProcessRun::query()
            ->where('status', 'failed')
            ->where('started_at', '>=', now()->subDay())
            ->count();

        $dueSoon = AutomaticProcess::query()
            ->where('enabled', true)
            ->where('next_run_at', '<=', now()->addHour())
            ->count();

        $lastFailed = AutomaticProcess::query()
            ->where('last_status', 'failed')
            ->orderByDesc('last_run_at')
            ->value('name');

        $scheduler = app(SchedulerStatus::class);
        $cron = $scheduler->cronHealth();
        $failedJobs = $scheduler->failedQueueJobsCount();

        return [
            Stat::make('Linux cron / scheduler', $cron['healthy'] ? 'Active' : 'Check server')
                ->description($cron['label'])
                ->color($cron['healthy'] ? 'success' : 'danger')
                ->url(AutomaticProcessResource::getUrl('index')),
            Stat::make('Enabled processes', (string) AutomaticProcess::query()->where('enabled', true)->count())
                ->description('Scheduled in database')
                ->color('success')
                ->url(AutomaticProcessResource::getUrl('index')),
            Stat::make('Failed runs (24h)', (string) $failed)
                ->description($lastFailed ? "Last: {$lastFailed}" : 'All clear')
                ->color($failed > 0 ? 'danger' : 'success')
                ->url(AutomaticProcessResource::getUrl('index')),
            Stat::make('Due within 1 hour', (string) $dueSoon)
                ->description('Next scheduler tick')
                ->color($dueSoon > 0 ? 'warning' : 'gray')
                ->url(AutomaticProcessResource::getUrl('index')),
            Stat::make('Failed queue jobs', (string) $failedJobs)
                ->description($failedJobs > 0 ? 'Run php artisan queue:retry all' : 'Queue healthy')
                ->color($failedJobs > 0 ? 'danger' : 'success'),
        ];
    }
}
