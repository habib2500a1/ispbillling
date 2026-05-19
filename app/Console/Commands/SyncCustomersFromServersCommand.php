<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncCustomersFromServersCommand extends Command
{
    protected $signature = 'isp:sync-customers-from-servers
                            {--server= : MikroTik server ID}
                            {--no-create : Do not create new subscribers}
                            {--no-update : Do not update existing subscribers}';

    protected $description = 'Sync all subscribers from MikroTik server(s) (ISP Digital: Sync All Customer By Servers)';

    public function handle(): int
    {
        $options = array_filter([
            '--server' => $this->option('server'),
            '--no-create' => $this->option('no-create'),
            '--no-update' => $this->option('no-update'),
        ], fn ($v): bool => $v !== null && $v !== false);

        return Artisan::call('isp:import-mikrotik-secrets', $options, $this->output);
    }
}
