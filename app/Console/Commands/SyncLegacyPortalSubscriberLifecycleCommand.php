<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Import\LegacyPortalSubscriberLifecycleSyncService;
use App\Support\CustomerStatus;
use App\Support\SubscriberType;
use Illuminate\Console\Command;

class SyncLegacyPortalSubscriberLifecycleCommand extends Command
{
    protected $signature = 'isp:sync-legacy-portal-lifecycle
                            {--tenant=1 : Tenant ID}
                            {--customer= : Only this customer_code or PPP login}
                            {--dry-run : Show what would change}
                            {--sync-network : Push MikroTik/RADIUS after status changes}
                            {--refresh : Pull latest subscriber list from legacy portal first}';

    protected $description = 'Sync suspended, VIP, free, inactive, expired, and left status from legacy portal snapshots';

    public function handle(LegacyPortalSubscriberLifecycleSyncService $sync): int
    {
        $tenantId = (int) $this->option('tenant');
        $filter = trim((string) $this->option('customer'));
        $dryRun = (bool) $this->option('dry-run');

        if ($this->option('refresh')) {
            $connection = array_filter([
                '--url' => config('legacy_portal.base_url'),
                '--user' => config('legacy_portal.username'),
                '--password' => config('legacy_portal.password'),
                '--force' => true,
            ], fn ($v): bool => $v !== null && $v !== '');

            $importOpts = $connection;
            if ($filter !== '') {
                $importOpts['--query'] = $filter;
            } else {
                $importOpts['--all'] = true;
                $importOpts['--batch'] = (int) config('legacy_portal.daily_sync_import_batch', 100);
            }

            $this->info('Refreshing subscriber list from legacy portal…');
            if ($this->call('isp:import-legacy-portal', $importOpts) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        if ($filter !== '') {
            $customer = Customer::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->fromLegacyPortal()
                ->where(function ($q) use ($filter): void {
                    $q->where('customer_code', $filter)
                        ->orWhere('radius_username', $filter)
                        ->orWhere('mikrotik_secret_name', $filter);
                })
                ->first();

            if ($customer === null) {
                $this->error("Subscriber not found: {$filter}");

                return self::FAILURE;
            }

            $result = $sync->syncCustomer(
                $customer,
                dryRun: $dryRun,
                syncNetwork: (bool) $this->option('sync-network'),
            );

            if ($result === null) {
                $this->warn('No legacy portal snapshot on this subscriber — run with --refresh.');

                return self::FAILURE;
            }

            $this->line($dryRun ? 'Would update: '.json_encode($result) : 'Updated: '.json_encode($result));
            $this->printListTotals($tenantId);

            return self::SUCCESS;
        }

        $stats = $sync->syncAll(
            $tenantId,
            $dryRun,
            (bool) $this->option('sync-network'),
        );

        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all(),
        );

        if ($dryRun) {
            $this->warn('Dry run — no rows changed. Re-run without --dry-run.');
        } else {
            $this->info('Lifecycle sync complete.');
        }

        $this->printListTotals($tenantId);

        return self::SUCCESS;
    }

    private function printListTotals(int $tenantId): void
    {
        $base = Customer::withoutGlobalScopes()->where('tenant_id', $tenantId);

        $this->newLine();
        $this->table(
            ['List', 'Count'],
            [
                ['Suspended (status)', (clone $base)->where('status', CustomerStatus::SUSPENDED)->count()],
                ['VIP', (clone $base)->where('subscriber_type', SubscriberType::VIP)->where('status', '!=', CustomerStatus::TERMINATED)->count()],
                ['Free', (clone $base)->where('subscriber_type', SubscriberType::FREE)->where('status', '!=', CustomerStatus::TERMINATED)->count()],
                ['Expired', (clone $base)->where('status', CustomerStatus::EXPIRED)->count()],
                ['Left', (clone $base)->where('status', CustomerStatus::TERMINATED)->count()],
                ['Legacy import', (clone $base)->fromLegacyPortal()->count()],
            ],
        );

        $inactiveSample = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->fromLegacyPortal()
            ->where('status', CustomerStatus::SUSPENDED)
            ->where(function ($q): void {
                $q->where('mikrotik_secret_name', 'ilike', '%ratan%')
                    ->orWhere('customer_code', 'ilike', '%329%');
            })
            ->limit(3)
            ->get(['customer_code', 'mikrotik_secret_name', 'status', 'subscriber_type']);

        if ($inactiveSample->isNotEmpty()) {
            $this->line('Sample suspended (ratan):');
            $this->table(
                ['Code', 'Login', 'Status', 'Type'],
                $inactiveSample->map(fn (Customer $c): array => [
                    $c->customer_code,
                    $c->mikrotik_secret_name ?? '—',
                    $c->status,
                    $c->subscriber_type,
                ])->all(),
            );
        }
    }
}
