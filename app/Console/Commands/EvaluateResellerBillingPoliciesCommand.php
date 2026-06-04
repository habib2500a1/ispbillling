<?php

namespace App\Console\Commands;

use App\Models\Reseller;
use App\Services\Resellers\ResellerBillingPolicyService;
use Illuminate\Console\Command;

class EvaluateResellerBillingPoliciesCommand extends Command
{
    protected $signature = 'isp:evaluate-reseller-billing-policies {--tenant=} {--dry-run}';

    protected $description = 'Evaluate reseller credit limits, grace periods, and apply suspension policies';

    public function handle(ResellerBillingPolicyService $policies): int
    {
        if (! config('reseller_billing.automation.evaluate_policies', true)) {
            $this->warn('Reseller billing policy evaluation is disabled in config.');

            return self::SUCCESS;
        }

        $tenantId = $this->option('tenant');
        $dryRun = (bool) $this->option('dry-run');

        $query = Reseller::query()->withoutGlobalScopes()->where('is_active', true);
        if ($tenantId !== null) {
            $query->where('tenant_id', (int) $tenantId);
        }

        $warned = 0;
        $suspended = 0;

        $query->orderBy('id')->chunkById(50, function ($resellers) use ($policies, $dryRun, &$warned, &$suspended): void {
            foreach ($resellers as $reseller) {
                $eval = $policies->evaluate($reseller);

                if ($eval['should_warn']) {
                    $warned++;
                    $this->line(sprintf(
                        '[warn] %s — due %s / limit %s (risk %.1f)',
                        $reseller->code,
                        number_format($eval['admin_due'], 2),
                        number_format($eval['credit_limit'], 2),
                        $eval['risk_score'],
                    ));
                }

                if ($eval['should_suspend_reseller']) {
                    if ($dryRun) {
                        $this->warn(sprintf('[dry-run] Would suspend %s', $reseller->code));
                        $suspended++;

                        continue;
                    }

                    if ($policies->applyResellerBreachIfNeeded($reseller->fresh())) {
                        $suspended++;
                        $this->error(sprintf('[suspend] %s — credit breach', $reseller->code));
                    }
                }
            }
        });

        $this->info("Evaluated resellers. Warnings: {$warned}, Suspended: {$suspended}");

        return self::SUCCESS;
    }
}
