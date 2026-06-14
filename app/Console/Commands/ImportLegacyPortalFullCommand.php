<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Support\CustomerBalanceDue;
use Illuminate\Console\Command;

/**
 * Full A→Z import from legacy portal (pay.anetbd.com): subscribers, packages, bill history, payments, balances.
 */
class ImportLegacyPortalFullCommand extends Command
{
    protected $signature = 'isp:import-legacy-portal-full
                            {--force : Update existing subscribers, invoices, and payments}
                            {--with-mirror : Raw-mirror source pages before importing}
                            {--skip-clients : Skip subscriber list import (use if already imported)}
                            {--skip-billing : Skip bill/payment history import}
                            {--with-details : Pull customer details HTML into meta (ONU MAC, network)}
                            {--with-onu : Include ONU rent/deposit from details in price sync}
                            {--skip-onu-pipeline : Skip OLT/ONU discovery/signal pipeline}
                            {--skip-staff : Skip HR employee import}
                            {--query=alloverclients : legacy portal list filter}
                            {--url= : Override LEGACY_PORTAL_URL}
                            {--user= : Override LEGACY_PORTAL_USERNAME}
                            {--password= : Override LEGACY_PORTAL_PASSWORD}';

    protected $description = 'Full legacy portal import: clients, packages, bill history, payments, current due';

    public function handle(): int
    {
        $password = (string) ($this->option('password') ?: config('legacy_portal.password'));
        if ($password === '') {
            $this->error('Set LEGACY_PORTAL_PASSWORD in .env');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $connection = array_filter([
            '--url' => $this->option('url'),
            '--user' => $this->option('user'),
            '--password' => $this->option('password'),
        ], fn ($v): bool => $v !== null && $v !== '');

        $common = array_merge($connection, array_filter([
            '--query' => $this->option('query'),
        ], fn ($v): bool => $v !== null && $v !== ''));

        $failed = false;

        if ($this->option('with-mirror')) {
            $this->info('Step 0 — Raw mirror source pages before import…');
            if ($this->call('isp:mirror-legacy-portal', array_merge($common, [
                '--with-customer-details' => true,
                '--with-history' => true,
            ])) !== self::SUCCESS) {
                $failed = true;
            }
        }

        if (! $this->option('skip-staff')) {
            $this->info('Step 1/8 — Import HR staff (employees) — before subscriber list…');
            if ($this->call('isp:import-legacy-portal-employees', array_merge($connection, [
                '--force' => $force,
            ])) !== self::SUCCESS) {
                $failed = true;
            }
        } else {
            $this->warn('Step 1/8 — Skipped staff import (--skip-staff).');
        }

        if (! $this->option('skip-clients')) {
            $this->info('Step 2/8 — Import all subscribers from legacy portal…');
            $exit = $this->call('isp:import-legacy-portal', array_merge($common, [
                '--all' => true,
                '--force' => $force,
                '--batch' => 100,
            ]));
            if ($exit !== self::SUCCESS) {
                $failed = true;
            }
        } else {
            $this->warn('Step 2/8 — Skipped subscriber import (--skip-clients).');
        }

        $this->info('Step 3/8 — Sync package profiles (MikroTik)…');
        if ($this->call('isp:sync-package-profiles-from-legacy-portal', $common) !== self::SUCCESS) {
            $failed = true;
        }

        $this->info('Step 4/8 — Sync package prices & monthly bills…');
        $priceOpts = array_merge($common, [
            '--with-onu-details' => (bool) $this->option('with-onu'),
        ]);
        if ($this->call('isp:sync-prices-from-legacy-portal', $priceOpts) !== self::SUCCESS) {
            $failed = true;
        }

        if (! $this->option('skip-billing')) {
            $this->info('Step 5/8 — Import bill history (payments + all invoices)…');
            $billingOpts = array_merge($connection, [
                '--force' => $force,
            ]);
            if ($this->call('isp:import-legacy-portal-billing', $billingOpts) !== self::SUCCESS) {
                $failed = true;
            }
        } else {
            $this->warn('Step 5/8 — Skipped billing history (--skip-billing).');
        }

        if (! $this->option('skip-billing')) {
            $this->info('Step 5b — Import any missing legacy portal collections…');
            $this->call('isp:sync-legacy-portal-collections', $connection);
        }

        $this->info('Step 6/8 — Sync current-month due/balance grid…');
        if ($this->call('isp:sync-legacy-portal-current-billing', $connection) !== self::SUCCESS) {
            $failed = true;
        }

        $this->info('Step 6b — Sync active/expired grace and line state…');
        if ($this->call('isp:sync-legacy-portal-line-grace', ['--resolve-alerts' => true]) !== self::SUCCESS) {
            $failed = true;
        }

        if ($this->option('with-details')) {
            $this->call('isp:sync-legacy-portal-details', array_merge($connection, [
                '--force' => $force,
            ]));
        }

        $this->info('Step 7/8 — Extras: resellers, SMS, app users, collectors, service invoices…');
        $this->call('isp:import-legacy-portal-extras', array_merge($connection, [
            '--force' => $force,
        ]));

        if (! $this->option('skip-onu-pipeline')) {
            $this->info('Step 8/8 — OLT/ONU inventory and signal pipeline…');
            $this->call('isp:legacy-portal-onu-sync');
        }

        $this->info('Reconcile invoice duplicates & payment totals…');
        $this->call('isp:reconcile-imported-billing');

        $this->reconcileLocalDueMeta();

        $this->newLine();
        $this->table(['', 'Count'], [
            ['Subscribers (legacy_portal)', Customer::query()->fromLegacyPortal()->count()],
            ['Employees', \App\Models\Employee::query()->count()],
            ['Invoices', \App\Models\Invoice::query()->count()],
            ['Payments', \App\Models\Payment::query()->count()],
            ['Details synced', Customer::query()->fromLegacyPortal()->whereNotNull('meta->legacy_portal_details_synced_at')->count()],
            ['SMS logs (legacy portal)', \App\Models\NotificationLog::query()->where('channel', 'sms')->whereNotNull('meta->legacy_portal_sms_log_id')->count()],
            ['Collectors assigned', Customer::query()->fromLegacyPortal()->whereNotNull('meta->collector_id')->count()],
            ['View', '/admin/subscribers'],
        ]);

        $this->info($failed ? 'Finished with errors — check output above.' : 'Full legacy portal import complete.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function reconcileLocalDueMeta(): void
    {
        $this->info('Refreshing local due balances from invoices…');
        $n = 0;
        Customer::query()
            ->fromLegacyPortal()
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$n): void {
                foreach ($chunk as $customer) {
                    CustomerBalanceDue::refreshMetaAfterPayment($customer);
                    $n++;
                }
            });
        $this->line("Due meta refreshed for {$n} subscribers.");
    }
}
