<?php

namespace App\Console\Commands;

use App\Services\Automation\AutomaticProcessScheduler;
use App\Services\Automation\SchedulerStatus;
use App\Support\Automation\SchedulerRunnerLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RunAutomaticProcessesCommand extends Command
{
    protected $signature = 'isp:run-automatic-processes {--force : Run all enabled processes regardless of schedule}';

    protected $description = 'Run due automatic processes (DB-driven scheduler)';

    public function handle(AutomaticProcessScheduler $scheduler): int
    {
        $maxRuntime = max(60, (int) config('automation.runner_lock_seconds', 300));
        set_time_limit($maxRuntime);

        $lock = SchedulerRunnerLock::acquire($maxRuntime);
        if ($lock === null) {
            $this->warn('Skipped — another automatic-process runner is already active.');

            return self::SUCCESS;
        }

        try {
            if (! Schema::hasTable('automatic_processes')) {
                $this->warn('Table automatic_processes missing — run php artisan migrate --force');

                return self::SUCCESS;
            }

            $force = (bool) $this->option('force');
            $ran = 0;

            $processes = $force
                ? \App\Models\AutomaticProcess::query()->withoutGlobalScopes()->where('enabled', true)->orderBy('sort_order')->get()
                : $scheduler->dueProcesses();

            foreach ($processes as $process) {
                try {
                    $lock->extend($maxRuntime);
                } catch (\Throwable) {
                    // ignore — lock may have been released
                }

                if ($scheduler->run($process, $force, $force ? 'manual' : 'scheduler')) {
                    $ran++;
                    $this->line("<info>Ran</info> {$process->name}");
                }
            }

            app(SchedulerStatus::class)->touchHeartbeat();

            $this->info("Automatic processes finished ({$ran} executed).");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('isp:run-automatic-processes failed', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            try {
                $lock->release();
            } catch (\Throwable) {
                // ignore
            }
            DB::disconnect();
        }
    }
}
