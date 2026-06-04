<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Support\CustomerBalanceDue;
use Illuminate\Console\Command;

class AlignLegacyPortalWithRemoteCommand extends Command
{
    protected $signature = 'isp:align-legacy-portal
                            {--skip-clients : Skip subscriber list refresh from legacy portal}
                            {--skip-network : Skip turning lines ON for ISP-active subscribers}
                            {--reopen-history : Reopen old consolidated monthly invoices (can inflate local due)}';

    protected $description = 'Match local due, bills, and active lines to legacy portal (pay.anetbd.com)';

    public function handle(): int
    {
        $connection = array_filter([
            '--url' => config('legacy_portal.base_url'),
            '--user' => config('legacy_portal.username'),
            '--password' => config('legacy_portal.password'),
        ], fn ($v): bool => $v !== null && $v !== '');

        if (! $this->option('skip-clients')) {
            $this->info('1/5 — Refresh subscribers + status from legacy portal…');
            $this->call('isp:import-legacy-portal', array_merge($connection, [
                '--all' => true,
                '--force' => true,
                '--batch' => 100,
            ]));
        }

        $this->info('2/6 — Sync current-month billing grid (due = ISP BalanceDue)…');
        $this->call('isp:sync-legacy-portal-current-billing', $connection);

        $this->info('3/6 — Match collection history with legacy portal…');
        $voidOrphans = config('legacy_portal.sync_collections_void_orphans', true);
        $this->call('isp:sync-legacy-portal-collections', array_merge($connection, 
            $voidOrphans ? ['--void-orphans' => true] : [],
        ));

        if ($this->option('reopen-history')) {
            $reopened = app(\App\Services\Import\LegacyPortalBillingReconciler::class)->reopenConsolidatedMonthlyInvoices(1);
            if ($reopened > 0) {
                $this->line("Reopened {$reopened} consolidated monthly invoice(s) for history/print.");
            }
        }

        $this->info('4/6 — Reconcile duplicate local monthly bills…');
        $this->call('isp:reconcile-imported-billing');

        $this->info('5/7 — Sync subscriber lifecycle (suspended / VIP / free / inactive)…');
        $this->call('isp:sync-legacy-portal-lifecycle');

        $this->info('6/7 — Line grace + clear false overdue session alerts…');
        $this->call('isp:sync-legacy-portal-line-grace', array_merge($connection, [
            '--resolve-alerts' => true,
        ]));

        if (! $this->option('skip-network')) {
            $this->info('7/7 — Network ON for ISP-active subscribers still suspended…');
            $this->call('isp:restore-legacy-portal-network', [
                '--grace-overdue' => true,
                '--no-snapshot' => true,
            ]);
            $this->call('isp:fix-stale-network');
        }

        $this->refreshDueMeta();

        $kpi = app(\App\Services\Mobile\StaffBillingKpiResolver::class)->resolve(1);

        $this->newLine();
        $this->table(['', 'BDT'], [
            ['Monthly bill', number_format((float) ($kpi['monthly_bill'] ?? 0), 2)],
            ['Collected', number_format((float) ($kpi['collected_bill'] ?? 0), 2)],
            ['Due', number_format((float) ($kpi['due'] ?? 0), 2)],
        ]);
        $this->table(['', 'Count'], [
            ['legacy portal subscribers', Customer::query()->fromLegacyPortal()->count()],
            ['Billing grid synced', Customer::query()->fromLegacyPortal()->whereNotNull('meta->legacy_portal_billing_synced_at')->count()],
            ['Network active', Customer::query()->fromLegacyPortal()->where('network_access_state', 'active')->count()],
            ['Network suspended', Customer::query()->fromLegacyPortal()->where('network_access_state', 'suspended')->count()],
        ]);

        $this->info('Aligned with legacy portal.');

        return self::SUCCESS;
    }

    private function refreshDueMeta(): void
    {
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
