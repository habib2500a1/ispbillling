<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class PruneLogsCommand extends Command
{
    protected $signature = 'isp:prune-logs';

    protected $description = 'Truncate oversized log files in storage/logs (prevents disk/RAM pressure)';

    public function handle(): int
    {
        $maxBytes = (int) config('automation.log_prune_max_bytes', 50_000_000);
        $keepBytes = (int) config('automation.log_prune_keep_bytes', 2_000_000);
        $dir = storage_path('logs');

        if (! is_dir($dir)) {
            return self::SUCCESS;
        }

        foreach (glob($dir.'/*.log') ?: [] as $path) {
            $size = @filesize($path);
            if ($size === false || $size <= $maxBytes) {
                continue;
            }

            $handle = @fopen($path, 'rb');
            if ($handle === false) {
                continue;
            }

            fseek($handle, -$keepBytes, SEEK_END);
            $tail = fread($handle, $keepBytes) ?: '';
            fclose($handle);

            file_put_contents($path, $tail);
            $this->line(sprintf('Pruned %s (%d → ~%d bytes)', basename($path), $size, strlen($tail)));
        }

        return self::SUCCESS;
    }
}
