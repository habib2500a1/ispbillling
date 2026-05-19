<?php

namespace App\Console\Commands;

use App\Jobs\PollMikrotikFleetJob;
use App\Services\Mikrotik\MikrotikFleetCoordinator;
use Illuminate\Console\Command;

class PollMikrotikServersCommand extends Command
{
    protected $signature = 'isp:mikrotik-poll-status';

    protected $description = 'Probe all MikroTik servers (API reachability) and store online/offline status.';

    public function handle(MikrotikFleetCoordinator $fleet): int
    {
        if (! config('mikrotik.poll_enabled', true)) {
            $this->info('MikroTik status polling is disabled (MIKROTIK_POLL_STATUS_ENABLED).');

            return self::SUCCESS;
        }

        if (config('queue_ops.heavy_jobs_enabled', false)) {
            PollMikrotikFleetJob::dispatch(null);
            $this->info('MikroTik poll queued (QUEUE_HEAVY_JOBS_ENABLED).');

            return self::SUCCESS;
        }

        $stats = $fleet->probeAllServers();

        foreach ($stats['servers'] as $row) {
            $status = $row['status'] === 'online' ? 'online' : 'offline';
            $this->line("{$row['name']} ({$row['host']}): {$status}");
        }

        $this->info("Polled {$stats['polled']} MikroTik server(s) — {$stats['online']} online, {$stats['offline']} offline.");

        return self::SUCCESS;
    }
}
