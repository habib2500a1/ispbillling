<?php

namespace App\Console\Commands;

use App\Services\Import\LegacyPortalCurrentBillingSyncService;
use App\Services\Import\LegacyPortalSessionClient;
use Illuminate\Console\Command;
use Throwable;

class SyncLegacyPortalCurrentBillingCommand extends Command
{
    protected $signature = 'isp:sync-legacy-portal-current-billing
                            {--url= : Override LEGACY_PORTAL_URL}
                            {--user= : Override LEGACY_PORTAL_USERNAME}
                            {--password= : Override LEGACY_PORTAL_PASSWORD}';

    protected $description = 'Sync current-month bills, due & collection from legacy portal billing grid (matches dashboard totals)';

    public function handle(): int
    {
        $baseUrl = (string) ($this->option('url') ?: config('legacy_portal.base_url'));
        $username = (string) ($this->option('user') ?: config('legacy_portal.username'));
        $password = (string) ($this->option('password') ?: config('legacy_portal.password'));

        if ($password === '') {
            $this->error('Set LEGACY_PORTAL_PASSWORD in .env');

            return self::FAILURE;
        }

        $this->info("Logging in to {$baseUrl}…");

        try {
            $client = new LegacyPortalSessionClient($baseUrl, $username, $password);
            $client->login();

            $this->info('Syncing current billing rows…');
            $result = app(LegacyPortalCurrentBillingSyncService::class)->syncAll($client);

            $s = $result['summary'];
            $this->newLine();
            $this->table(['Metric', 'BDT'], [
                ['Monthly bill (legacy portal)', number_format($s['monthly_bill'] ?? 0, 2)],
                ['Collected (legacy portal)', number_format($s['collected_bill'] ?? 0, 2)],
                ['Due (legacy portal)', number_format($s['due'] ?? 0, 2)],
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
