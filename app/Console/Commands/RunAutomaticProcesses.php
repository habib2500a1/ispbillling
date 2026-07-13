<?php

namespace App\Console\Commands;

use App\Services\Automation\AutomaticProcessScheduler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class RunAutomaticProcesses extends Command
{
    protected $signature = 'cpagol:run-automatic-processes {--force : Run all enabled processes regardless of schedule}';

    protected $description = 'Run due automatic processes (DB-driven scheduler)';

    public function handle(AutomaticProcessScheduler $scheduler): int
    {
        if (! Schema::hasTable('automatic_processes')) {
            $this->warn('Table automatic_processes missing — run php artisan migrate --force');

            return self::SUCCESS;
        }

        $lock = Cache::lock('cpagol:automatic-process-runner', 300);
        if (! $lock->get()) {
            $this->warn('Skipped — another runner is active.');

            return self::SUCCESS;
        }

        try {
            $force = (bool) $this->option('force');
            $ran = 0;

            $processes = $force
                ? \App\Models\AutomaticProcess::query()->where('enabled', true)->orderBy('sort_order')->get()
                : $scheduler->dueProcesses();

            foreach ($processes as $process) {
                if ($scheduler->run($process, $force, $force ? 'manual' : 'scheduler')) {
                    $ran++;
                    $this->line("<info>Ran</info> {$process->name}");
                }
            }

            $this->info("Automatic processes finished ({$ran} executed).");

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
