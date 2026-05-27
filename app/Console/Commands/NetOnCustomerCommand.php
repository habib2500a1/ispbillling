<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Short alias: turn one subscriber ON on MikroTik (admin rescue).
 */
class NetOnCustomerCommand extends Command
{
    protected $signature = 'isp:net-on
                            {customer : Customer ID, code (0605), or phone}
                            {--tenant=1 : Tenant ID}';

    protected $description = 'MikroTik PPP secret ON now (same as network-sync-customer --set-active --force-mikrotik).';

    public function handle(): int
    {
        return $this->call('isp:network-sync-customer', [
            'customer' => $this->argument('customer'),
            '--tenant' => $this->option('tenant'),
            '--set-active' => true,
            '--force-mikrotik' => true,
        ]);
    }
}
