<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Import\LegacyPortalCustomerDetailsSyncService;
use App\Services\Import\LegacyPortalSessionClient;
use Illuminate\Console\Command;
use Throwable;

class SyncLegacyPortalDetailsCommand extends Command
{
    protected $signature = 'isp:sync-legacy-portal-details
                            {--customer= : Only this customer_code}
                            {--force : Re-sync even if already synced}
                            {--url= : Override LEGACY_PORTAL_URL}
                            {--user= : Override LEGACY_PORTAL_USERNAME}
                            {--password= : Override LEGACY_PORTAL_PASSWORD}';

    protected $description = 'Sync legacy portal customer details (ONU MAC, rent, network fields) into subscriber meta';

    public function handle(): int
    {
        $password = (string) ($this->option('password') ?: config('legacy_portal.password'));
        if ($password === '') {
            $this->error('Set LEGACY_PORTAL_PASSWORD in .env');

            return self::FAILURE;
        }

        $baseUrl = (string) ($this->option('url') ?: config('legacy_portal.base_url'));
        $username = (string) ($this->option('user') ?: config('legacy_portal.username'));
        $codeFilter = trim((string) $this->option('customer'));
        $force = (bool) $this->option('force');

        $query = Customer::query()->fromLegacyPortal()->orderBy('id');
        if ($codeFilter !== '') {
            $query->where('customer_code', $codeFilter);
        }
        if (! $force) {
            $query->whereNull('meta->legacy_portal_details_synced_at');
        }

        $customers = $query->get();
        if ($customers->isEmpty()) {
            $this->info('No subscribers pending details sync.');

            return self::SUCCESS;
        }

        $this->info('Syncing details for '.$customers->count().' subscribers…');
        $client = new LegacyPortalSessionClient($baseUrl, $username, $password);
        $client->login();
        $service = new LegacyPortalCustomerDetailsSyncService($client);

        $bar = $this->output->createProgressBar($customers->count());
        $bar->start();

        $updated = 0;
        $errors = 0;
        $i = 0;

        foreach ($customers as $customer) {
            if ($i > 0 && $i % 40 === 0) {
                $client = new LegacyPortalSessionClient($baseUrl, $username, $password);
                $client->login();
                $service = new LegacyPortalCustomerDetailsSyncService($client);
            }
            $i++;

            try {
                $result = $service->syncCustomer($customer);
                if ($result['updated']) {
                    $updated++;
                }
                if ($result['error'] !== null) {
                    $errors++;
                }
            } catch (Throwable $e) {
                $errors++;
                $this->newLine();
                $this->warn("  {$customer->customer_code}: {$e->getMessage()}");
            }
            $bar->advance();
            usleep(60_000);
        }

        $bar->finish();
        $this->newLine();
        $this->line("Updated: {$updated}, errors: {$errors}");

        $withOnu = Customer::query()
            ->fromLegacyPortal()
            ->where(function ($q): void {
                $q->whereNotNull('meta->onu_mac')->orWhereNotNull('meta->onu_rent');
            })
            ->count();
        $this->line("Subscribers with ONU meta: {$withOnu}");

        return self::SUCCESS;
    }
}
