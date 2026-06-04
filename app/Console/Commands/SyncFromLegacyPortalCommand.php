<?php

namespace App\Console\Commands;

use App\Services\Import\LegacyPortalPriceSyncService;
use App\Services\Import\LegacyPortalSessionClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * One-shot legacy portal sync: packages, user monthly bills, package prices, billing due, optional ONU meta.
 */
class SyncFromLegacyPortalCommand extends Command
{
    protected $signature = 'isp:sync-from-legacy-portal
                            {--query=alloverclients : legacy portal list filter}
                            {--with-onu-details : Pull ONU rent/deposit from customer details (slower)}
                            {--skip-billing : Skip current-month due/balance sync}
                            {--profiles-only : Only sync package MikroTik profile names}
                            {--url= : Override LEGACY_PORTAL_URL}
                            {--user= : Override LEGACY_PORTAL_USERNAME}
                            {--password= : Override LEGACY_PORTAL_PASSWORD}';

    protected $description = 'Full legacy portal sync (packages, prices, bills, balances)';

    public function handle(LegacyPortalPriceSyncService $prices): int
    {
        if ($this->option('profiles-only')) {
            return $this->call('isp:sync-package-profiles-from-legacy-portal', array_filter([
                '--query' => $this->option('query'),
                '--url' => $this->option('url'),
                '--user' => $this->option('user'),
                '--password' => $this->option('password'),
            ]));
        }

        $baseUrl = (string) ($this->option('url') ?: config('legacy_portal.base_url'));
        $username = (string) ($this->option('user') ?: config('legacy_portal.username'));
        $password = (string) ($this->option('password') ?: config('legacy_portal.password'));

        if ($password === '') {
            $this->error('Set LEGACY_PORTAL_PASSWORD in .env');

            return self::FAILURE;
        }

        $this->info("legacy portal sync → {$baseUrl}");

        try {
            $client = new LegacyPortalSessionClient($baseUrl, $username, $password);

            $this->info('1/2 Package profiles…');
            $profilesExit = $this->call('isp:sync-package-profiles-from-legacy-portal', array_filter([
                '--query' => $this->option('query'),
                '--url' => $this->option('url'),
                '--user' => $this->option('user'),
                '--password' => $this->option('password'),
            ]));
            if ($profilesExit !== self::SUCCESS) {
                return $profilesExit;
            }

            $this->info('2/2 Prices, packages, customer bills…');
            $stats = $prices->syncAll($client, (string) $this->option('query'), (bool) $this->option('with-onu-details'));

            $this->table(['', 'Count'], [
                ['Customer bills', $stats['customers_updated']],
                ['Package prices', $stats['packages_updated']],
                ['ONU meta', $stats['onu_updated']],
                ['Failed', $stats['failed']],
            ]);

            if (! $this->option('skip-billing')) {
                $this->info('Billing due/balance…');
                $billingExit = $this->call('isp:sync-legacy-portal-current-billing', array_filter([
                    '--url' => $this->option('url'),
                    '--user' => $this->option('user'),
                    '--password' => $this->option('password'),
                ]));

                if ($billingExit !== self::SUCCESS) {
                    return $billingExit;
                }
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
