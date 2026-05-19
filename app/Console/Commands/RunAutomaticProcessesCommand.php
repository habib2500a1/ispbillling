<?php

namespace App\Console\Commands;

use App\Services\Automation\AutomaticProcessScheduler;
use App\Services\Automation\SchedulerStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RunAutomaticProcessesCommand extends Command
{
    protected $signature = 'isp:run-automatic-processes {--force : Run all enabled processes regardless of schedule}';

    protected $description = 'Run due automatic processes (DB-driven scheduler)';

    public function handle(AutomaticProcessScheduler $scheduler): int
    {
        set_time_limit(300);

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
                if ($scheduler->run($process, $force, $force ? 'manual' : 'scheduler')) {
                    $ran++;
                    $this->line("<info>Ran</info> {$process->name}");
                }
            }

            app(SchedulerStatus::class)->touchHeartbeat();

            $this->info("Automatic processes finished ({$ran} executed).");

            return self::SUCCESS;
        } finally {
            DB::disconnect();
        }
    }
}
