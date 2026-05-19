<?php

namespace App\Console\Commands;

use App\Jobs\ImportMikrotikFleetJob;
use App\Services\Mikrotik\MikrotikFleetCoordinator;
use App\Support\QueueJobDispatcher;
use Illuminate\Console\Command;

class ImportMikrotikPppSecretsCommand extends Command
{
    protected $signature = 'isp:import-mikrotik-secrets
                            {--server= : MikroTik server ID (default: all enabled)}
                            {--no-create : Do not create new subscribers}
                            {--no-update : Do not update existing subscribers}
                            {--queued : Internal: skip re-queue}';

    protected $description = 'Import PPP secrets from MikroTik router(s) into subscribers';

    public function handle(MikrotikFleetCoordinator $fleet): int
    {
        if (! config('subscriber.auto_import_secrets_enabled', false) && ! $this->option('server')) {
            $this->warn('Pass --server=ID to import, or set SUBSCRIBER_AUTO_IMPORT_MIKROTIK_SECRETS=true for scheduled runs.');

            return self::SUCCESS;
        }

        $onlyServerId = $this->option('server') ? (int) $this->option('server') : null;

        $options = [
            'create_missing' => ! $this->option('no-create'),
            'update_existing' => ! $this->option('no-update'),
        ];

        if (config('queue_ops.heavy_jobs_enabled', false) && ! $this->option('queued')) {
            $jobOpts = ['--queued' => true];
            if ($onlyServerId) {
                $jobOpts['--server'] = $onlyServerId;
            }
            if ($this->option('no-create')) {
                $jobOpts['--no-create'] = true;
            }
            if ($this->option('no-update')) {
                $jobOpts['--no-update'] = true;
            }
            QueueJobDispatcher::run(
                new ImportMikrotikFleetJob($jobOpts),
                fn () => $fleet->importAllServers(null, $onlyServerId, $options),
            );
            $this->info('MikroTik import queued.');

            return self::SUCCESS;
        }

        $result = $fleet->importAllServers(null, $onlyServerId, $options);

        foreach ($result['by_server'] as $row) {
            $this->info("{$row['name']} ({$row['host']}): created {$row['created']}, updated {$row['updated']}, skipped {$row['skipped']}");
        }

        foreach (array_slice($result['errors'], 0, 10) as $err) {
            $this->error($err);
        }

        $this->info(sprintf(
            'Done. Created %d, updated %d, skipped %d across %d router(s).',
            $result['created'],
            $result['updated'],
            $result['skipped'],
            count($result['by_server']),
        ));

        return self::SUCCESS;
    }
}
