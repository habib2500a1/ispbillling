<?php

namespace App\Console\Commands;

use App\Services\Resellers\ResellerMonthlyStatementService;
use Illuminate\Console\Command;

class CloseResellerMonthlyStatementsCommand extends Command
{
    protected $signature = 'isp:close-reseller-monthly-statements {--tenant=} {--dry-run}';

    protected $description = 'Sync and close previous month admin receivable statements for all resellers';

    public function handle(ResellerMonthlyStatementService $statements): int
    {
        if (! config('reseller_billing.monthly_statements.auto_close', true)) {
            $this->warn('Monthly statement auto-close is disabled.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $ref = now()->subMonth();
            $this->info(sprintf('Would close statements for %s %d', $ref->format('F'), $ref->year));

            return self::SUCCESS;
        }

        $tenantId = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;
        $result = $statements->closePreviousMonthForAll($tenantId);

        $this->info("Closed monthly statements for {$result['closed']} reseller(s).");

        return self::SUCCESS;
    }
}
