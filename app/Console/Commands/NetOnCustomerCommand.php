<?php

namespace App\Console\Commands;

use App\Services\Network\NetworkAccessCoordinator;
use App\Support\CustomerNetworkSync;
use Illuminate\Console\Command;

/**
 * Short alias: turn one subscriber ON on MikroTik (admin rescue).
 */
class NetOnCustomerCommand extends Command
{
    protected $signature = 'isp:net-on
                            {customer : Customer code (0705), PPP login (parvej.b), or DB id}
                            {--tenant=1 : Tenant ID}';

    protected $description = 'MikroTik PPP secret ON now (fast — no heavy sync job).';

    public function handle(): int
    {
        $tenantId = (int) ($this->option('tenant') ?: 1);
        $input = trim((string) $this->argument('customer'));

        $customer = app(NetworkSyncCustomerCommand::class)->resolveCustomerForNetOn($tenantId, $input);

        if ($customer === null) {
            $this->error("Customer not found (tenant {$tenantId}, lookup: {$input}).");

            return self::FAILURE;
        }

        $coordinator = app(\App\Services\Network\NetworkAccessCoordinator::class);

        if (! $coordinator->canAdminForceNetOn($customer)) {
            $this->error('Cannot enable: overdue invoice. Collect payment first.');

            return self::FAILURE;
        }

        $this->info("Enabling {$customer->pppLoginName()} (code {$customer->customer_code}, id {$customer->id})…");

        $started = microtime(true);
        CustomerNetworkSync::forceNetOn($customer);
        $elapsed = round(microtime(true) - $started, 2);

        $this->info("Done in {$elapsed}s. Check MikroTik /ppp/secret «{$customer->pppLoginName()}» → disabled=no.");

        return self::SUCCESS;
    }
}
