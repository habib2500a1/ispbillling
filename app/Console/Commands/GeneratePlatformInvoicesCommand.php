<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Tenant\PlatformInvoiceBillingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GeneratePlatformInvoicesCommand extends Command
{
    protected $signature = 'isp:generate-platform-invoices
                            {--date= : Reference date (Y-m-d), default today}
                            {--tenant= : Limit to one tenant ID}
                            {--force : Ignore billing_day filter}
                            {--dry-run : Show what would be created}';

    protected $description = 'Generate monthly SaaS platform invoices for ISP tenants on their subscription bill day';

    public function handle(PlatformInvoiceBillingService $billing): int
    {
        $date = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : now()->startOfDay();
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $tenantId = $this->option('tenant');

        if ($tenantId) {
            $tenant = Tenant::query()->findOrFail((int) $tenantId);
            $result = $billing->generateForTenantIfDue($tenant, $date, $force, $dryRun);
            $this->info($result === 'created'
                ? "Platform invoice ".($dryRun ? 'would be created' : 'created')." for {$tenant->name}."
                : "Skipped {$tenant->name} (not due or already billed for {$date->format('Y-m')}).");

            return self::SUCCESS;
        }

        $stats = $billing->generateDue($date, $force, $dryRun);
        $verb = $dryRun ? 'Would create' : 'Created';

        $this->info("{$verb} {$stats['created']} platform invoice(s); skipped {$stats['skipped']}.");
        if ($stats['tenants'] !== []) {
            $this->line('Tenants: '.implode(', ', $stats['tenants']));
        }

        return self::SUCCESS;
    }
}
