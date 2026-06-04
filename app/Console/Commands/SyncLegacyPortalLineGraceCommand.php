<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\MikrotikSessionAlert;
use App\Services\Import\LegacyPortalCurrentBillingSyncService;
use App\Services\Import\LegacyPortalOverdueEvaluator;
use App\Services\Import\LegacyPortalSessionClient;
use Illuminate\Console\Command;

class SyncLegacyPortalLineGraceCommand extends Command
{
    protected $signature = 'isp:sync-legacy-portal-line-grace
                            {--resolve-alerts : Resolve false overdue_still_online session alerts}';

    protected $description = 'Apply legacy portal bill-day / EffectiveTo line grace for all imported subscribers and clear false overdue session alerts';

    public function handle(): int
    {
        $baseUrl = (string) config('legacy_portal.base_url');
        $username = (string) config('legacy_portal.username');
        $password = (string) config('legacy_portal.password');

        if ($password === '') {
            $this->error('Set LEGACY_PORTAL_PASSWORD in .env');

            return self::FAILURE;
        }

        $client = new LegacyPortalSessionClient($baseUrl, $username, $password);
        $this->info('Logging in to legacy portal…');
        $client->login();

        $this->info('Syncing billing grid (due + line grace)…');
        $stats = app(LegacyPortalCurrentBillingSyncService::class)->syncAll($client);

        $this->table(['', 'Count'], [
            ['Customers synced', $stats['customers']],
            ['Skipped', $stats['skipped']],
        ]);

        $evaluator = app(LegacyPortalOverdueEvaluator::class);
        $backfilled = $this->backfillGraceForCustomersNotOnGrid($evaluator);
        if ($backfilled > 0) {
            $this->line("Bill-day grace backfilled for {$backfilled} subscriber(s) not on ISP billing grid.");
        }

        $graced = Customer::query()
            ->fromLegacyPortal()
            ->get()
            ->filter(fn (Customer $c): bool => \App\Services\Billing\CustomerLineGraceService::hasActiveLineGrace($c))
            ->count();

        $this->line("Active line grace: {$graced} subscriber(s).");

        if ($this->option('resolve-alerts')) {
            $resolved = $this->resolveFalseOverdueAlerts($evaluator);
            $this->info("Resolved {$resolved} false overdue session alert(s).");
        }

        return self::SUCCESS;
    }

    private function backfillGraceForCustomersNotOnGrid(LegacyPortalOverdueEvaluator $evaluator): int
    {
        $count = 0;

        Customer::query()
            ->fromLegacyPortal()
            ->where('status', 'active')
            ->where(function ($q): void {
                $q->whereNull('meta->legacy_portal_billing_synced_at')
                    ->orWhere('meta->legacy_portal_billing_synced_at', '');
            })
            ->each(function (Customer $customer) use ($evaluator, &$count): void {
                $day = max(1, min(31, (int) ($customer->billing_day ?? 1)));
                $evaluator->syncLineGraceFromBillingRow($customer, [
                    'BillingLastDate' => (string) $day,
                    'BalanceDue' => '1',
                    'Disabled' => false,
                    'Status' => 'Active',
                    'EffectiveTo' => '',
                ]);
                $count++;
            });

        return $count;
    }

    private function resolveFalseOverdueAlerts(LegacyPortalOverdueEvaluator $evaluator): int
    {
        $resolved = 0;

        MikrotikSessionAlert::query()
            ->where('alert_type', MikrotikSessionAlert::TYPE_OVERDUE_ONLINE)
            ->whereNull('resolved_at')
            ->with('customer')
            ->each(function (MikrotikSessionAlert $alert) use ($evaluator, &$resolved): void {
                $customer = $alert->customer;
                if ($customer === null) {
                    return;
                }

                if (! \App\Support\LegacyPortalSource::isImportedSource($customer->import_source ?? null)) {
                    return;
                }

                if ($evaluator->shouldAlertOverdueOnlineSession($customer)) {
                    return;
                }

                $alert->forceFill(['resolved_at' => now()])->save();
                $resolved++;
            });

        return $resolved;
    }
}
