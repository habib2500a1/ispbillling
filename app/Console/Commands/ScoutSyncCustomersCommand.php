<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Tenant;
use App\Services\Search\CustomerSearchConfigurator;
use Illuminate\Console\Command;

class ScoutSyncCustomersCommand extends Command
{
    protected $signature = 'isp:scout-sync-customers
                            {--tenant= : Limit import to one tenant id}
                            {--fresh : Flush index before import}';

    protected $description = 'Sync Meilisearch customer index (500k-scale full-text search for tickets, bill collection, mobile)';

    public function handle(): int
    {
        CustomerSearchConfigurator::apply();

        // Bulk CLI import must run synchronously; queued jobs may sit behind billing/network work.
        config(['scout.queue' => false]);

        if (! in_array((string) config('scout.driver'), ['meilisearch', 'collection'], true)) {
            $this->error('Customer search disabled or Scout not available. Enable in Settings → Customer search.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->warn('Flushing customers index…');
            $this->call('scout:flush', ['model' => Customer::class]);
        }

        $this->info('Applying Meilisearch index settings…');
        $this->call('scout:sync-index-settings');

        $tenantId = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;

        if ($tenantId !== null) {
            $count = Customer::withoutGlobalScopes()->where('tenant_id', $tenantId)->count();
            $this->info("Importing {$count} customers for tenant #{$tenantId}…");
            Customer::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->searchable();
        } else {
            $tenantIds = Tenant::query()->pluck('id');
            foreach ($tenantIds as $tid) {
                $count = Customer::withoutGlobalScopes()->where('tenant_id', $tid)->count();
                $this->line("Tenant #{$tid}: {$count} customers");
            }
            $this->info('Importing all customers (chunked)…');
            $this->call('scout:import', ['model' => Customer::class]);
        }

        $this->info('Customer search index sync complete.');

        return self::SUCCESS;
    }
}
