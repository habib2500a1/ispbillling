<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class SchedulerGuardCommand extends Command
{
    protected $signature = 'isp:scheduler-guard
                            {--dry-run : Report only, do not kill stale processes}';

    protected $description = 'Prevent stacked scheduler workers from starving PHP-FPM (web 502)';

    public function handle(): int
    {
        $maxProcesses = max(1, (int) config('automation.max_runner_processes', 1));
        $staleAfter = max(120, (int) config('automation.stale_runner_seconds', 360));

        $rows = $this->runnerProcesses();
        $count = count($rows);

        $this->line("isp:run-automatic-processes workers: {$count} (max {$maxProcesses})");

        if ($count <= $maxProcesses) {
            return self::SUCCESS;
        }

        $stale = array_values(array_filter(
            $rows,
            fn (array $row): bool => $row['elapsed'] >= $staleAfter,
        ));

        if ($stale === []) {
            $this->warn('Too many workers but none older than '.$staleAfter.'s — manual check advised.');

            return self::FAILURE;
        }

        foreach ($stale as $row) {
            $msg = sprintf(
                'Stale scheduler worker PID %d (elapsed %ds) %s',
                $row['pid'],
                $row['elapsed'],
                $this->option('dry-run') ? '(dry-run)' : '→ terminating',
            );
            $this->warn($msg);

            if (! $this->option('dry-run')) {
                posix_kill($row['pid'], SIGTERM);
                Log::warning('scheduler.guard_killed_stale_worker', $row);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return list<array{pid: int, elapsed: int, cmd: string}>
     */
    private function runnerProcesses(): array
    {
        $output = shell_exec("ps -eo pid=,etimes=,args= 2>/dev/null | grep '[a]rtisan isp:run-automatic-processes'") ?? '';

        $rows = [];

        foreach (explode("\n", trim($output)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (! preg_match('/^(\d+)\s+(\d+)\s+(.+)$/', $line, $m)) {
                continue;
            }

            $rows[] = [
                'pid' => (int) $m[1],
                'elapsed' => (int) $m[2],
                'cmd' => $m[3],
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['elapsed'] <=> $a['elapsed']);

        return $rows;
    }
}
