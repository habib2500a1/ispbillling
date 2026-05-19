<?php

namespace App\Console\Commands;

use App\Services\Network\NetflowAbuseEvaluator;
use App\Services\Network\NetflowIngestService;
use App\Support\TenantResolver;
use Illuminate\Console\Command;

class ProcessNetflowInboxCommand extends Command
{
    protected $signature = 'isp:process-netflow-inbox';

    protected $description = 'Import NetFlow JSON batches from storage/app/netflow/inbox';

    public function handle(NetflowIngestService $ingest): int
    {
        if (! config('netflow.enabled')) {
            $this->warn('NetFlow disabled.');

            return self::SUCCESS;
        }

        $inserted = $ingest->processInboxFiles();
        $purged = $ingest->purgeOldFlows();

        $this->info("Imported {$inserted} flow(s); purged {$purged} old record(s).");

        if (config('netflow.abuse_eval_enabled', true)) {
            $alerts = app(NetflowAbuseEvaluator::class)->evaluateTenant(TenantResolver::requiredTenantId());
            if ($alerts > 0) {
                $this->info("NetFlow abuse: {$alerts} new alert(s).");
            }
        }

        return self::SUCCESS;
    }
}
