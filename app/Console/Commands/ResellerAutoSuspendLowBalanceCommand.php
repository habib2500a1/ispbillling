<?php

namespace App\Console\Commands;

use App\Models\Reseller;
use App\Services\Resellers\ResellerAutomationService;
use Illuminate\Console\Command;

class ResellerAutoSuspendLowBalanceCommand extends Command
{
    protected $signature = 'isp:reseller-auto-suspend-low-balance {--tenant=}';

    protected $description = 'Suspend resellers with auto_suspend_on_low_balance when wallet is below threshold.';

    public function handle(ResellerAutomationService $automation): int
    {
        if (! config('reseller_enterprise.automation.low_balance_suspend', true)) {
            $this->info('Low-balance auto suspend is disabled.');

            return self::SUCCESS;
        }

        $query = Reseller::query()
            ->withoutGlobalScopes()
            ->where('is_active', true)
            ->where('auto_suspend_on_low_balance', true);

        if ($this->option('tenant')) {
            $query->where('tenant_id', (int) $this->option('tenant'));
        }

        $count = 0;
        foreach ($query->cursor() as $reseller) {
            $before = $reseller->is_active;
            $automation->evaluateLowBalance($reseller);
            if ($before && ! $reseller->fresh()->is_active) {
                $count++;
                $this->line("Suspended {$reseller->code} ({$reseller->name})");
            }
        }

        $this->info("Evaluated resellers; suspended {$count} account(s).");

        return self::SUCCESS;
    }
}
