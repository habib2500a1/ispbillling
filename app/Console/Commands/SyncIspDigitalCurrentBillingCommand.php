<?php

namespace App\Console\Commands;

use App\Services\Import\IspDigitalCurrentBillingSyncService;
use App\Services\Import\IspDigitalSessionClient;
use Illuminate\Console\Command;
use Throwable;

class SyncIspDigitalCurrentBillingCommand extends Command
{
    protected $signature = 'isp:sync-isp-digital-current-billing
                            {--url= : Override ISP_DIGITAL_URL}
                            {--user= : Override ISP_DIGITAL_USERNAME}
                            {--password= : Override ISP_DIGITAL_PASSWORD}';

    protected $description = 'Sync current-month bills, due & collection from ISP Digital billing grid (matches dashboard totals)';

    public function handle(): int
    {
        $baseUrl = (string) ($this->option('url') ?: config('isp_digital.base_url'));
        $username = (string) ($this->option('user') ?: config('isp_digital.username'));
        $password = (string) ($this->option('password') ?: config('isp_digital.password'));

        if ($password === '') {
            $this->error('Set ISP_DIGITAL_PASSWORD in .env');

            return self::FAILURE;
        }

        $this->info("Logging in to {$baseUrl}…");

        try {
            $client = new IspDigitalSessionClient($baseUrl, $username, $password);
            $client->login();

            $this->info('Syncing current billing rows…');
            $result = app(IspDigitalCurrentBillingSyncService::class)->syncAll($client);

            $s = $result['summary'];
            $this->newLine();
            $this->table(['Metric', 'BDT'], [
                ['Monthly bill (ISP Digital)', number_format($s['monthly_bill'] ?? 0, 2)],
                ['Collected (ISP Digital)', number_format($s['collected_bill'] ?? 0, 2)],
                ['Due (ISP Digital)', number_format($s['due'] ?? 0, 2)],
                ['Discount', number_format($s['discount'] ?? 0, 2)],
            ]);
            $this->table(['', 'Count'], [
                ['Customers synced', $result['customers']],
                ['Invoices updated', $result['invoices']],
                ['Skipped (no local match)', $result['skipped']],
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
