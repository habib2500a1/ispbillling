<?php

namespace App\Services\Automation;

use App\Models\AutomaticProcess;
use App\Models\AutomaticProcessRun;
use App\Services\Automation\AutomaticProcessFailureNotifier;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\BufferedOutput;

final class AutomaticProcessScheduler
{
    public function isDue(AutomaticProcess $process, ?Carbon $now = null): bool
    {
        $now = $now ?? now();

        if (! $process->enabled) {
            return false;
        }

        if ($process->when_config_key !== null && $process->when_config_key !== '' && ! (bool) config($process->when_config_key)) {
            return false;
        }

        if ($process->next_run_at === null) {
            return true;
        }

        return $now->greaterThanOrEqualTo($process->next_run_at);
    }

    public function computeNextRunAt(AutomaticProcess $process, ?Carbon $after = null): Carbon
    {
        $after = $after ?? now();

        return match ($process->interval) {
            'every_minute' => $after->copy()->addMinute()->startOfMinute(),
            'every_two_minutes' => $this->alignToMinuteInterval($after, 2),
            'every_three_minutes' => $this->alignToMinuteInterval($after, 3),
            'every_five_minutes' => $this->alignToMinuteInterval($after, 5),
            'every_ten_minutes' => $this->alignToMinuteInterval($after, 10),
            'every_fifteen_minutes' => $this->alignToMinuteInterval($after, 15),
            'every_thirty_minutes' => $this->alignToMinuteInterval($after, 30),
            'hourly' => $after->copy()->addHour()->startOfHour(),
            'daily' => $this->nextDailyAt($process->execute_at, $after),
            default => $this->nextDailyAt($process->execute_at, $after),
        };
    }

    public function run(AutomaticProcess $process, bool $force = false, string $triggeredBy = 'scheduler'): bool
    {
        if (! $force && ! $this->isDue($process)) {
            return false;
        }

        if ($process->when_config_key !== null && $process->when_config_key !== '' && ! (bool) config($process->when_config_key)) {
            $this->finishSkipped($process, 'Disabled by config: '.$process->when_config_key, $triggeredBy);

            return false;
        }

        $lockMinutes = $process->without_overlapping_minutes;
        $lock = $lockMinutes
            ? Cache::lock('automatic-process:'.$process->id, $lockMinutes * 60)
            : null;

        if ($lock !== null && ! $lock->get()) {
            return false;
        }

        $startedAt = now();
        $run = AutomaticProcessRun::query()->create([
            'automatic_process_id' => $process->id,
            'triggered_by' => $triggeredBy,
            'status' => 'running',
            'started_at' => $startedAt,
        ]);

        $process->forceFill(['last_status' => 'running'])->save();

        $output = new BufferedOutput;
        $exitCode = 1;

        try {
            $exitCode = Artisan::call(
                $process->artisan_command,
                $process->command_options ?? [],
                $output,
            );
        } catch (\Throwable $e) {
            Log::error('Automatic process failed', [
                'process' => $process->slug,
                'error' => $e->getMessage(),
            ]);

            $this->finishRun($process, $run, $startedAt, 'failed', $exitCode, $e->getMessage(), $triggeredBy);
            $lock?->release();

            return false;
        }

        $body = trim($output->fetch());
        $status = $exitCode === 0 ? 'success' : 'failed';
        $this->finishRun(
            $process,
            $run,
            $startedAt,
            $status,
            $exitCode,
            $body !== '' ? $body : 'Exit code: '.$exitCode,
            $triggeredBy,
        );

        $lock?->release();

        return $exitCode === 0;
    }

    /**
     * @return list<AutomaticProcess>
     */
    public function dueProcesses(): array
    {
        if (! Schema::hasTable('automatic_processes')) {
            return [];
        }

        return AutomaticProcess::query()
            ->withoutGlobalScopes()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (AutomaticProcess $p): bool => $this->isDue($p))
            ->values()
            ->all();
    }

    private function finishSkipped(AutomaticProcess $process, string $message, string $triggeredBy): void
    {
        AutomaticProcessRun::query()->create([
            'automatic_process_id' => $process->id,
            'triggered_by' => $triggeredBy,
            'exit_code' => 0,
            'status' => 'skipped',
            'output' => mb_substr($message, 0, 4000),
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $process->forceFill([
            'last_status' => 'skipped',
            'last_output' => mb_substr($message, 0, 4000),
            'next_run_at' => $this->computeNextRunAt($process),
        ])->save();
    }

    private function finishRun(
        AutomaticProcess $process,
        AutomaticProcessRun $run,
        Carbon $startedAt,
        string $status,
        int $exitCode,
        string $output,
        string $triggeredBy,
    ): void {
        $finishedAt = now();

        $run->forceFill([
            'status' => $status,
            'exit_code' => $exitCode,
            'output' => mb_substr($output, 0, 8000),
            'finished_at' => $finishedAt,
        ])->save();

        $process->forceFill([
            'last_run_at' => $startedAt,
            'last_status' => $status,
            'last_output' => mb_substr($output, 0, 4000),
            'next_run_at' => $this->computeNextRunAt($process, $finishedAt),
        ])->save();

        if ($status === 'failed') {
            app(AutomaticProcessFailureNotifier::class)->notify($process, $run->fresh() ?? $run);
        }

        $this->pruneRunHistory($process);
    }

    private function pruneRunHistory(AutomaticProcess $process): void
    {
        $keep = (int) config('automation.run_history_keep', 100);
        $ids = $process->runs()->orderByDesc('id')->skip($keep)->pluck('id');

        if ($ids->isNotEmpty()) {
            AutomaticProcessRun::query()->whereIn('id', $ids)->delete();
        }
    }

    private function alignToMinuteInterval(Carbon $after, int $minutes): Carbon
    {
        $next = $after->copy()->addMinute()->startOfMinute();
        $remainder = $next->minute % $minutes;

        if ($remainder !== 0) {
            $next->addMinutes($minutes - $remainder);
        }

        return $next;
    }

    private function nextDailyAt(?string $time, Carbon $after): Carbon
    {
        $parts = explode(':', $time ?: '00:00');
        $hour = (int) ($parts[0] ?? 0);
        $minute = (int) ($parts[1] ?? 0);

        $next = $after->copy()->setTime($hour, $minute, 0);

        if ($next->lessThanOrEqualTo($after)) {
            $next->addDay();
        }

        return $next;
    }
}
