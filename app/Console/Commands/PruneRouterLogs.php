<?php

namespace App\Console\Commands;

use App\Http\Controllers\MikrotikController;
use App\Models\MainSiteData;
use Illuminate\Console\Command;

class PruneRouterLogs extends Command
{
    protected $signature = 'cpagol:prune-router-logs';

    protected $description = 'Prune old MikroTik router logs';

    public function handle(): int
    {
        $days = (int) MainSiteData::getValue('log_retention_days', 30);
        app(MikrotikController::class)->pruneOldLogs($days);
        $this->info("Router logs pruned (retention: {$days} days).");

        return self::SUCCESS;
    }
}
