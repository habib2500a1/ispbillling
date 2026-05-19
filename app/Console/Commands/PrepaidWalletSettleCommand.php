<?php

namespace App\Console\Commands;

use App\Services\Automation\PrepaidWalletAutoSettleService;
use Illuminate\Console\Command;

class PrepaidWalletSettleCommand extends Command
{
    protected $signature = 'isp:prepaid-wallet-settle
                            {--tenant= : Tenant ID}
                            {--dry-run : Simulate without saving}';

    protected $description = 'Apply prepaid/advance wallet to open invoices and extend service expiry when paid.';

    public function handle(PrepaidWalletAutoSettleService $service): int
    {
        if (! config('automation.prepaid_wallet_settle.enabled', true)) {
            $this->info('Prepaid wallet auto-settle is disabled.');

            return self::SUCCESS;
        }

        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;
        $dryRun = (bool) $this->option('dry-run');

        $stats = $service->settleForTenant($tenantId, $dryRun);

        $this->info(sprintf(
            '%sCustomers: %d · Applied: %.2f BDT · Invoices: %d · Renewed: %d',
            $dryRun ? '[dry-run] ' : '',
            $stats['customers'],
            $stats['applied'],
            $stats['invoices'],
            $stats['renewed'],
        ));

        return self::SUCCESS;
    }
}
