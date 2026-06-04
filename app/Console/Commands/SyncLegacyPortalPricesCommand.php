<?php

namespace App\Console\Commands;

use App\Services\Import\LegacyPortalPriceSyncService;
use App\Services\Import\LegacyPortalSessionClient;
use Illuminate\Console\Command;
use Throwable;

class SyncLegacyPortalPricesCommand extends Command
{
    protected $signature = 'isp:sync-prices-from-legacy-portal
                            {--query=alloverclients : legacy portal list filter}
                            {--with-onu-details : Pull ONU rent/deposit from customer details HTML (slower)}
                            {--with-billing : Also sync current-month due/balance}
                            {--url= : Override LEGACY_PORTAL_URL}
                            {--user= : Override LEGACY_PORTAL_USERNAME}
                            {--password= : Override LEGACY_PORTAL_PASSWORD}';

    protected $description = 'Set package price_monthly and per-user monthly bill from legacy portal (PackageSpeed + MonthlyBill)';

    public function handle(LegacyPortalPriceSyncService $sync): int
    {
        $baseUrl = (string) ($this->option('url') ?: config('legacy_portal.base_url'));
        $username = (string) ($this->option('user') ?: config('legacy_portal.username'));
        $password = (string) ($this->option('password') ?: config('legacy_portal.password'));

        if ($password === '') {
            $this->error('Set LEGACY_PORTAL_PASSWORD in .env or pass --password=');

            return self::FAILURE;
        }

        $this->info("Logging in to {$baseUrl}…");

        try {
            $client = new LegacyPortalSessionClient($baseUrl, $username, $password);
            $stats = $sync->syncAll($client, (string) $this->option('query'), (bool) $this->option('with-onu-details'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['', 'Count'], [
            ['Customer bills updated', $stats['customers_updated']],
            ['Customers unchanged', $stats['customers_skipped']],
            ['Package prices updated', $stats['packages_updated']],
            ['ONU meta updated (details)', $stats['onu_updated']],
            ['ONU/details skipped', $stats['onu_skipped']],
            ['Not in local DB', $stats['missing']],
            ['Failed', $stats['failed']],
        ]);

        if ($this->option('with-billing')) {
            $this->newLine();
            $this->call('isp:sync-legacy-portal-current-billing', array_filter([
                '--url' => $this->option('url'),
                '--user' => $this->option('user'),
                '--password' => $this->option('password'),
            ]));
        }

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
