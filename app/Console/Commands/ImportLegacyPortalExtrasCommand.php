<?php

namespace App\Console\Commands;

use App\Services\Import\LegacyPortalApplicationUserImporter;
use App\Services\Import\LegacyPortalBillingImporter;
use App\Services\Import\LegacyPortalCollectorSyncService;
use App\Services\Import\LegacyPortalSessionClient;
use App\Services\Import\LegacyPortalSmsImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportLegacyPortalExtrasCommand extends Command
{
    protected $signature = 'isp:import-legacy-portal-extras
                            {--force : Re-import SMS logs and refresh service invoices}
                            {--skip-sms : Skip SMS message history}
                            {--skip-users : Skip application user import}
                            {--skip-collectors : Skip collector assignment from payments}
                            {--skip-invoices : Skip service invoice re-sync}
                            {--skip-resellers : Skip MAC reseller import}
                            {--url= : Override LEGACY_PORTAL_URL}
                            {--user= : Override LEGACY_PORTAL_USERNAME}
                            {--password= : Override LEGACY_PORTAL_PASSWORD}';

    protected $description = 'Import legacy portal extras: SMS history, app users (collectors), collector assign, service invoices';

    public function handle(): int
    {
        $baseUrl = (string) ($this->option('url') ?: config('legacy_portal.base_url'));
        $username = (string) ($this->option('user') ?: config('legacy_portal.username'));
        $password = (string) ($this->option('password') ?: config('legacy_portal.password'));

        if ($password === '') {
            $this->error('Set LEGACY_PORTAL_PASSWORD in .env');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');

        if (! $this->option('skip-resellers')) {
            $this->info('Importing MAC resellers (partners)…');
            if ($this->call('isp:import-legacy-portal-resellers', array_merge(
                array_filter([
                    '--url' => $this->option('url'),
                    '--user' => $this->option('user'),
                    '--password' => $this->option('password'),
                ], fn ($v): bool => $v !== null && $v !== ''),
                ['--force' => $force],
            )) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        if (! $this->option('skip-users')) {
            $this->info('Importing application users (collector/staff logins)…');
            $userClient = new LegacyPortalSessionClient($baseUrl, $username, $password);
            $userClient->login();
            $userStats = (new LegacyPortalApplicationUserImporter)->importAll($userClient, $force);
            $this->table(['Users', 'Count'], [
                ['Imported', $userStats['imported']],
                ['Updated', $userStats['updated']],
                ['Skipped', $userStats['skipped']],
            ]);
        }

        if (! $this->option('skip-invoices')) {
            $this->info('Re-syncing service invoices (legacy portal has '.$this->remoteInvoiceTotal($baseUrl, $username, $password).' rows)…');
            $invClient = new LegacyPortalSessionClient($baseUrl, $username, $password);
            $invClient->login();
            $importer = new LegacyPortalBillingImporter;
            $customers = $importer->customersByLegacyHeaderId();
            $invStats = $importer->importServiceInvoices($invClient, $customers, $force);
            $this->line("Service invoices touched: {$invStats['invoices']}, skipped: {$invStats['skipped']}");
        }

        if (! $this->option('skip-sms')) {
            $this->info('Importing SMS / message history per subscriber…');
            $smsClient = new LegacyPortalSessionClient($baseUrl, $username, $password);
            $smsClient->login();
            $bar = $this->output->createProgressBar(
                (new LegacyPortalBillingImporter)->customersByLegacyHeaderId()->count(),
            );
            $bar->start();

            $smsImporter = new LegacyPortalSmsImporter;
            $smsStats = ['imported' => 0, 'skipped' => 0];
            $i = 0;

            foreach ((new LegacyPortalBillingImporter)->customersByLegacyHeaderId() as $headerId => $customer) {
                if ($i > 0 && $i % 40 === 0) {
                    $smsClient = new LegacyPortalSessionClient($baseUrl, $username, $password);
                    $smsClient->login();
                }
                $i++;

                try {
                    $row = $smsImporter->importCustomer($smsClient, $customer, (int) $headerId, $force);
                    $smsStats['imported'] += $row['imported'];
                    $smsStats['skipped'] += $row['skipped'];
                } catch (Throwable $e) {
                    $this->newLine();
                    $this->warn("  {$customer->customer_code}: {$e->getMessage()}");
                }
                $bar->advance();
                usleep(50_000);
            }

            $bar->finish();
            $this->newLine();
            $this->line("SMS imported: {$smsStats['imported']}, skipped: {$smsStats['skipped']}");
        }

        if (! $this->option('skip-collectors')) {
            $this->info('Linking collectors from payment ReceivedBy…');
            $collectorStats = (new LegacyPortalCollectorSyncService)->syncAll();
            $this->line("Customers with collector set: {$collectorStats['customers_updated']}, no match: {$collectorStats['unmatched']}");
        }

        $this->newLine();
        $this->table(['', 'Count'], [
            ['SMS logs (imported)', \App\Models\NotificationLog::query()->where('meta->import_source', 'legacy_portal')->count()],
            ['Users (legacyportal+*@import.local)', \App\Models\User::query()->where('email', 'like', 'legacyportal+%@import.local')->count()],
            ['Customers with collector_id', \App\Models\Customer::query()->whereNotNull('meta->collector_id')->count()],
            ['Service invoices ISD-SINV-*', \App\Models\Invoice::query()->where('invoice_number', 'like', 'ISD-SINV-%')->count()],
        ]);

        return self::SUCCESS;
    }

    private function remoteInvoiceTotal(string $baseUrl, string $username, string $password): int
    {
        $client = new LegacyPortalSessionClient($baseUrl, $username, $password);
        $client->login();

        return $client->fetchServiceInvoicePage(0, 100)['iTotalDisplayRecords'];
    }
}
