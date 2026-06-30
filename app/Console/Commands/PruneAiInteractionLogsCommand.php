<?php

namespace App\Console\Commands;

use App\Models\AiInteractionLog;
use Illuminate\Console\Command;

class PruneAiInteractionLogsCommand extends Command
{
    protected $signature = 'isp:ai-prune-logs
                            {--days= : Retention days (default from config ai.audit_retention_days)}';

    protected $description = 'Delete AI interaction audit logs older than the retention window.';

    public function handle(): int
    {
        $days = max(7, (int) ($this->option('days') ?: config('ai.audit_retention_days', 90)));
        $cutoff = now()->subDays($days);

        $deleted = AiInteractionLog::query()
            ->withoutGlobalScopes()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} AI interaction log(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
