<?php

namespace App\Console\Commands;

use App\Services\Import\IspDigitalPriceSyncService;
use App\Services\Import\IspDigitalSessionClient;
use Illuminate\Console\Command;
use Throwable;

class SyncIspDigitalPricesCommand extends Command
{
    protected $signature = 'isp:sync-prices-from-isp-digital
                            {--query=alloverclients : ISP Digital list filter}
                            {--with-onu-details : Pull ONU rent/deposit from customer details HTML (slower)}
                            {--with-billing : Also sync current-month due/balance}
                            {--url= : Override ISP_DIGITAL_URL}
                            {--user= : Override ISP_DIGITAL_USERNAME}
                            {--password= : Override ISP_DIGITAL_PASSWORD}';

    protected $description = 'Set package price_monthly and per-user monthly bill from ISP Digital (PackageSpeed + MonthlyBill)';

    public function handle(IspDigitalPriceSyncService $sync): int
    {
        $baseUrl = (string) ($this->option('url') ?: config('isp_digital.base_url'));
        $username = (string) ($this->option('user') ?: config('isp_digital.username'));
        $password = (string) ($this->option('password') ?: config('isp_digital.password'));

        if ($password === '') {
            $this->error('Set ISP_DIGITAL_PASSWORD in .env or pass --password=');

            return self::FAILURE;
        }

        $this->info("Logging in to {$baseUrl}…");

        try {
            $client = new IspDigitalSessionClient($baseUrl, $username, $password);
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
            $this->call('isp:sync-isp-digital-current-billing', array_filter([
                '--url' => $this->option('url'),
                '--user' => $this->option('user'),
                '--password' => $this->option('password'),
            ]));
        }

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
