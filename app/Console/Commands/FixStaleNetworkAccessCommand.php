<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\MikrotikServer;
use App\Services\Mikrotik\MikrotikServerService;
use App\Services\Network\NetworkAccessCoordinator;
use App\Support\CustomerNetworkSync;
use Illuminate\Console\Command;
use RouterOS\Query;

class FixStaleNetworkAccessCommand extends Command
{
    protected $signature = 'isp:fix-stale-network
                            {--tenant=1 : Tenant ID}
                            {--dry-run : List only, do not push}';

    protected $description = 'Fix subscribers who should be ON (valid, not overdue) but MikroTik PPP secret is disabled or DB network is stale suspended.';

    public function handle(
        NetworkAccessCoordinator $coordinator,
        MikrotikServerService $mikrotik,
    ): int {
        $tenantId = (int) ($this->option('tenant') ?: 1);
        $dryRun = (bool) $this->option('dry-run');

        $server = MikrotikServer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_enabled', true)
            ->first();

        if ($server === null) {
            $this->error('No enabled MikroTik server for tenant '.$tenantId);

            return self::FAILURE;
        }

        $disabledOnRouter = $this->disabledSecretNames($mikrotik, $server);
        $toFix = [];

        Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', ['terminated', 'left'])
            ->orderBy('customer_code')
            ->chunk(100, function ($customers) use ($coordinator, $disabledOnRouter, &$toFix): void {
                foreach ($customers as $customer) {
                    if ($customer->isServiceExpired() || $coordinator->hasOverdueOpenBalance($customer)) {
                        continue;
                    }

                    if ($coordinator->desiredNetworkAccessState($customer) !== 'active') {
                        continue;
                    }

                    $login = $customer->pppLoginName();
                    $staleDb = ($customer->network_access_state ?? 'active') === 'suspended';
                    $secretOff = $login !== '' && isset($disabledOnRouter[$login]);

                    if ($staleDb || $secretOff) {
                        $toFix[] = $customer;
                    }
                }
            });

        if ($toFix === []) {
            $this->info('No stale network / disabled-secret mismatches found.');

            return self::SUCCESS;
        }

        $this->info('Found '.count($toFix).' customer(s) to fix:');
        $this->table(
            ['Code', 'PPP login', 'DB network', 'Secret on router'],
            array_map(fn (Customer $c) => [
                $c->customer_code,
                $c->pppLoginName(),
                $c->network_access_state ?? '—',
                isset($disabledOnRouter[$c->pppLoginName()]) ? 'disabled' : 'ok/enabled',
            ], $toFix),
        );

        if ($dryRun) {
            $this->warn('Dry run — no changes made.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar(count($toFix));
        $bar->start();

        $fixed = 0;
        foreach ($toFix as $customer) {
            try {
                CustomerNetworkSync::forceNetOn($customer);
                $fixed++;
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("Failed {$customer->customer_code} ({$customer->pppLoginName()}): {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Fixed {$fixed} / ".count($toFix).' customer(s).');

        return self::SUCCESS;
    }

    /**
     * @return array<string, true>
     */
    private function disabledSecretNames(MikrotikServerService $mikrotik, MikrotikServer $server): array
    {
        $client = $mikrotik->makeClient($server);
        $rows = $client->query('/ppp/secret/print')->read();
        $map = [];

        if (! is_array($rows)) {
            return $map;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['disabled'] ?? '') === 'true' && ($row['service'] ?? '') === 'pppoe') {
                $map[(string) ($row['name'] ?? '')] = true;
            }
        }

        return $map;
    }
}
