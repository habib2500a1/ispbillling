<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Optical\CustomerOnuAutoProvisionService;
use Illuminate\Console\Command;

class EnsureCustomerOnusCommand extends Command
{
    protected $signature = 'isp:ensure-customer-onus {--tenant= : Tenant ID} {--limit=500 : Max customers per tenant}';

    protected $description = 'Auto-create/link ONU device rows for subscribers missing optical inventory';

    public function handle(CustomerOnuAutoProvisionService $provision): int
    {
        if (! config('optical.auto_provision_customer_onu', true)) {
            $this->warn('Customer ONU auto-provision is disabled (OPTICAL_AUTO_PROVISION_ONU=false).');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $tenantIds = $this->option('tenant')
            ? [(int) $this->option('tenant')]
            : Tenant::query()->pluck('id')->all();

        $totalCreated = 0;
        $totalLinked = 0;
        $totalSkipped = 0;

        foreach ($tenantIds as $tenantId) {
            $stats = $provision->provisionMissingForTenant((int) $tenantId, $limit);
            $totalCreated += $stats['created'];
            $totalLinked += $stats['linked'];
            $totalSkipped += $stats['skipped'];
            $this->line("Tenant #{$tenantId}: created {$stats['created']}, linked {$stats['linked']}, skipped {$stats['skipped']}");
        }

        $this->info("Done. Created {$totalCreated}, linked {$totalLinked}, skipped {$totalSkipped}.");

        return self::SUCCESS;
    }
}
