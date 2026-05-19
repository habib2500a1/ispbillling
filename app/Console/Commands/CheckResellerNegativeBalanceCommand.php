<?php

namespace App\Console\Commands;

use App\Models\Reseller;
use Illuminate\Console\Command;

class CheckResellerNegativeBalanceCommand extends Command
{
    protected $signature = 'isp:check-reseller-negative-balance
                            {--tenant= : Limit to tenant id}
                            {--deactivate : Deactivate resellers with negative wallet}';

    protected $description = 'List MAC/franchise resellers with negative wallet balance; optionally deactivate them.';

    public function handle(): int
    {
        if (! config('automation.reseller_negative_balance.enabled', true)) {
            $this->info('Reseller balance check is disabled (automation.reseller_negative_balance.enabled).');

            return self::SUCCESS;
        }

        $query = Reseller::query()->withoutGlobalScopes()->where('wallet_balance', '<', 0);

        if ($this->option('tenant')) {
            $query->where('tenant_id', (int) $this->option('tenant'));
        }

        $resellers = $query->orderBy('wallet_balance')->get();

        if ($resellers->isEmpty()) {
            $this->info('No resellers with negative wallet balance.');

            return self::SUCCESS;
        }

        $deactivate = $this->option('deactivate')
            || config('automation.reseller_negative_balance.auto_deactivate', false);

        foreach ($resellers as $reseller) {
            $this->line(sprintf(
                '%s (%s): wallet %s BDT',
                $reseller->name,
                $reseller->code,
                number_format((float) $reseller->wallet_balance, 2),
            ));

            if ($deactivate && $reseller->is_active) {
                $reseller->forceFill(['is_active' => false])->save();
                $this->warn("  → deactivated {$reseller->code}");
            }
        }

        $this->info("Found {$resellers->count()} reseller(s) with negative balance.");

        return self::SUCCESS;
    }
}
