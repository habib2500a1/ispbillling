<?php

namespace App\Console\Commands;

use App\Models\MikrotikServer;
use App\Services\Mikrotik\MikrotikServerService;
use Illuminate\Console\Command;

class FetchMikrotikDetailsCommand extends Command
{
    protected $signature = 'isp:mikrotik-fetch-details {--server= : MikroTik server ID (optional; default: all enabled)}';

    protected $description = 'Fetch RouterOS identity/resource snapshot via API into mikrotik_servers.meta (api_detail).';

    public function handle(MikrotikServerService $service): int
    {
        if (! config('mikrotik.fetch_details_poll_enabled', false)) {
            $this->info('MikroTik detail fetch poll is disabled (MIKROTIK_FETCH_DETAILS_POLL_ENABLED).');

            return self::SUCCESS;
        }

        $q = MikrotikServer::query()->withoutGlobalScopes()->where('is_enabled', true)->orderBy('id');
        if ($this->option('server') !== null) {
            $q->whereKey((int) $this->option('server'));
        }

        $count = 0;
        foreach ($q->cursor() as $server) {
            $service->fetchRouterDetails($server);
            $count++;
        }

        $this->info("Fetched details for {$count} MikroTik server(s).");

        return self::SUCCESS;
    }
}
