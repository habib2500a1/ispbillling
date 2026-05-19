<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class AutoLinkCustomerOnusCommand extends Command
{
    protected $signature = 'isp:auto-link-customer-onus {--tenant= : Tenant ID}';

    protected $description = 'Alias for isp:smart-link-customer-onus';

    public function handle(): int
    {
        return Artisan::call('isp:smart-link-customer-onus', [
            '--tenant' => $this->option('tenant'),
        ], $this->output);
    }
}
