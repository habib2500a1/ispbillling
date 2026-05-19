<?php

namespace App\Console\Commands;

use App\Services\Automation\PostpaidWalletAutoApplyService;
use App\Services\Network\NetworkAccessCoordinator;
use App\Models\Customer;
use Illuminate\Console\Command;

class PostpaidPopFundCreditCommand extends Command
{
    protected $signature = 'isp:postpaid-pop-fund-credit
                            {--tenant= : Limit to tenant id}
                            {--dry-run : Preview wallet applications only}
                            {--skip-suspend : Do not re-evaluate network access after apply}';

    protected $description = 'Apply postpaid customer wallet to open invoices, then suspend still-unpaid (ISP Digital parity)';

    public function handle(
        PostpaidWalletAutoApplyService $walletApply,
        NetworkAccessCoordinator $network,
    ): int {
        if (! config('automation.postpaid_fund_credit.enabled', true)) {
            $this->info('Postpaid fund credit is disabled (automation.postpaid_fund_credit.enabled).');

            return self::SUCCESS;
        }

        $tenantId = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;
        $dryRun = (bool) $this->option('dry-run');

        $stats = $walletApply->applyForTenant($tenantId, $dryRun);

        $this->info(sprintf(
            '%s: %d customer(s), %s BDT on %d invoice(s)',
            $dryRun ? 'Would apply' : 'Applied',
            $stats['customers'],
            number_format($stats['applied'], 2),
            $stats['invoices'],
        ));

        if ($dryRun || $this->option('skip-suspend')) {
            return self::SUCCESS;
        }

        if (! config('network.auto_suspend_enabled', false)) {
            $this->comment('Auto suspend is off — skipped disable-unpaid pass.');

            return self::SUCCESS;
        }

        $suspended = 0;
        $query = Customer::query()
            ->withoutGlobalScopes()
            ->where('billing_mode', 'postpaid');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $query->orderBy('id')->chunkById(200, function ($customers) use ($network, &$suspended): void {
            foreach ($customers as $customer) {
                $before = $customer->network_access_state;
                $network->syncCustomer($customer->fresh() ?? $customer);
                $after = ($customer->fresh() ?? $customer)->network_access_state;
                if ($before !== 'suspended' && $after === 'suspended') {
                    $suspended++;
                }
            }
        });

        $this->info("Postpaid network re-check: {$suspended} newly suspended.");

        return self::SUCCESS;
    }
}
