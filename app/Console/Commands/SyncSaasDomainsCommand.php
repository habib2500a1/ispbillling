<?php

namespace App\Console\Commands;

use App\Services\Saas\CaddyDomainSync;
use Illuminate\Console\Command;

class SyncSaasDomainsCommand extends Command
{
    protected $signature = 'saas:sync-domains';

    protected $description = 'Publish saved ISP domains to the shared Caddy proxy';

    public function handle(CaddyDomainSync $sync): int
    {
        $added = $sync->sync();
        $this->info('Published '.$added.' ISP domain(s) to Caddy.');

        return self::SUCCESS;
    }
}
