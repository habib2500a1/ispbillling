<?php

namespace App\Console\Commands;

use App\Services\Billing\FupUsageAlertService;
use Illuminate\Console\Command;

class SendFupUsageAlertsCommand extends Command
{
    protected $signature = 'isp:send-fup-usage-alerts
                            {--tenant= : Tenant ID}
                            {--dry-run : Log only}';

    protected $description = 'Warn subscribers approaching or exceeding FUP data allowance before billing.';

    public function handle(FupUsageAlertService $alerts): int
    {
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $stats = $alerts->run($tenantId, (bool) $this->option('dry-run'));

        $this->info(sprintf(
            '%sWarnings: %d, critical: %d, skipped: %d.',
            $this->option('dry-run') ? '[dry-run] ' : '',
            $stats['warnings'],
            $stats['critical'],
            $stats['skipped'],
        ));

        return self::SUCCESS;
    }
}
