<?php

namespace App\Console\Commands;

use App\Services\Billing\ScheduledPackageChangeService;
use Illuminate\Console\Command;

class ApplyScheduledPackageChangesCommand extends Command
{
    protected $signature = 'isp:apply-scheduled-package-changes
                            {--tenant= : Tenant ID}
                            {--dry-run : Simulate only}';

    protected $description = 'Apply pending package downgrades/changes scheduled for today or earlier.';

    public function handle(ScheduledPackageChangeService $service): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $stats = $service->applyDueChanges($tenantId, (bool) $this->option('dry-run'));

        $this->info(sprintf(
            '%sApplied %d package change(s), skipped %d.',
            $this->option('dry-run') ? '[dry-run] ' : '',
            $stats['applied'],
            $stats['skipped'],
        ));

        return self::SUCCESS;
    }
}
