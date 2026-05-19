<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Optical\CustomerOnuSmartLinkService;
use Illuminate\Console\Command;

class SmartLinkCustomerOnusCommand extends Command
{
    protected $signature = 'isp:smart-link-customer-onus
                            {--tenant= : Tenant ID}
                            {--no-reset : Do not remove wrong/placeholder links first}';

    protected $description = 'Smart ONU↔subscriber linking (exact login/EPON/MAC only, no fuzzy guesses)';

    public function handle(CustomerOnuSmartLinkService $linker): int
    {
        $reset = ! $this->option('no-reset');
        $tenantIds = $this->option('tenant')
            ? [(int) $this->option('tenant')]
            : Tenant::query()->pluck('id')->all();

        foreach ($tenantIds as $tenantId) {
            $result = $linker->smartRelinkTenant((int) $tenantId, $reset);
            $this->line(sprintf(
                'Tenant #%d: pruned %d wrong · linked %d · skipped %d ambiguous · conflicts %d',
                $tenantId,
                $result['pruned'],
                $result['linked'],
                $result['skipped'],
                $result['conflicts'],
            ));
            foreach ($result['by_reason'] as $reason => $count) {
                $this->line("  · {$reason}: {$count}");
            }
        }

        $this->info('Smart link complete. Only high-confidence matches were applied.');

        return self::SUCCESS;
    }
}
