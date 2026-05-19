<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class LinkCustomerOnusByMacCommand extends Command
{
    protected $signature = 'isp:link-customer-onus-by-mac {--tenant= : Tenant ID} {--limit=2000 : Max customers}';

    protected $description = 'Alias: auto-link ONUs by MAC + PPP login (use isp:auto-link-customer-onus)';

    public function handle(): int
    {
        return Artisan::call('isp:auto-link-customer-onus', [
            '--tenant' => $this->option('tenant'),
            '--limit' => $this->option('limit'),
        ], $this->output);
    }
}
