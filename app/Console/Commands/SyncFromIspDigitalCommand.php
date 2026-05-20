<?php

namespace App\Console\Commands;

use App\Services\Import\IspDigitalPriceSyncService;
use App\Services\Import\IspDigitalSessionClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * One-shot ISP Digital sync: packages, user monthly bills, package prices, billing due, optional ONU meta.
 */
class SyncFromIspDigitalCommand extends Command
{
    protected $signature = 'isp:sync-from-isp-digital
                            {--query=alloverclients : ISP Digital list filter}
                            {--with-onu-details : Pull ONU rent/deposit from customer details (slower)}
                            {--skip-billing : Skip current-month due/balance sync}
                            {--profiles-only : Only sync package MikroTik profile names}
                            {--url= : Override ISP_DIGITAL_URL}
                            {--user= : Override ISP_DIGITAL_USERNAME}
                            {--password= : Override ISP_DIGITAL_PASSWORD}';

    protected $description = 'Full ISP Digital sync (packages, prices, bills, balances)';

    public function handle(IspDigitalPriceSyncService $prices): int
    {
        if ($this->option('profiles-only')) {
            return $this->call('isp:sync-package-profiles-from-isp-digital', array_filter([
                '--query' => $this->option('query'),
                '--url' => $this->option('url'),
                '--user' => $this->option('user'),
                '--password' => $this->option('password'),
            ]));
        }

        $baseUrl = (string) ($this->option('url') ?: config('isp_digital.base_url'));
        $username = (string) ($this->option('user') ?: config('isp_digital.username'));
        $password = (string) ($this->option('password') ?: config('isp_digital.password'));

        if ($password === '') {
            $this->error('Set ISP_DIGITAL_PASSWORD in .env');

            return self::FAILURE;
        }

        $this->info("ISP Digital sync → {$baseUrl}");

        try {
            $client = new IspDigitalSessionClient($baseUrl, $username, $password);

            $this->info('1/2 Package profiles…');
            $profilesExit = $this->call('isp:sync-package-profiles-from-isp-digital', array_filter([
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
                $billingExit = $this->call('isp:sync-isp-digital-current-billing', array_filter([
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
