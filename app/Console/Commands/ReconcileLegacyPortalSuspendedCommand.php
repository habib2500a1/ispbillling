<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Import\LegacyPortalSubscriberStatusReconciler;
use App\Support\CustomerStatus;
use Illuminate\Console\Command;

class ReconcileLegacyPortalSuspendedCommand extends Command
{
    protected $signature = 'isp:reconcile-legacy-portal-suspended
                            {--tenant=1 : Tenant ID}
                            {--customer= : Only this customer_code or PPP login}
                            {--dry-run : List mismatches only}
                            {--no-network : Update DB only, do not push MikroTik/RADIUS}';

    protected $description = 'Set suspended status for subscribers inactive in legacy portal but still active locally';

    public function handle(LegacyPortalSubscriberStatusReconciler $reconciler): int
    {
        $tenantId = (int) $this->option('tenant');
        $dryRun = (bool) $this->option('dry-run');
        $syncNetwork = ! (bool) $this->option('no-network');
        $filter = trim((string) $this->option('customer'));

        $query = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->fromLegacyPortal()
            ->where('status', CustomerStatus::ACTIVE);

        if ($filter !== '') {
            $query->where(function ($q) use ($filter): void {
                $q->where('customer_code', $filter)
                    ->orWhere('radius_username', $filter)
                    ->orWhere('mikrotik_secret_name', $filter);
            });
        }

        $rows = $query->orderBy('customer_code')->get()->filter(
            fn (Customer $c): bool => $reconciler->shouldBeSuspended($c),
        );

        if ($rows->isEmpty()) {
            $this->info('No active-local / ISP-inactive mismatches.');

            return self::SUCCESS;
        }

        $this->warn('Found '.$rows->count().' subscriber(s) that should be suspended:');
        $this->table(
            ['Code', 'Login', 'Name', 'ISP Status'],
            $rows->map(fn (Customer $c): array => [
                $c->customer_code,
                $c->radius_username ?? $c->mikrotik_secret_name ?? '—',
                $c->name,
                $this->ispStatusLabel($c),
            ])->all(),
        );

        if ($dryRun) {
            $this->line('Dry run — no changes. Run without --dry-run to fix.');

            return self::SUCCESS;
        }

        $fixed = 0;
        foreach ($rows as $customer) {
            if ($reconciler->reconcileOne($customer, $syncNetwork)) {
                $fixed++;
            }
        }

        $suspendedTotal = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', CustomerStatus::SUSPENDED)
            ->count();

        $this->newLine();
        $this->info("Suspended {$fixed} account(s). Suspended list total: {$suspendedTotal}.");

        return self::SUCCESS;
    }

    private function ispStatusLabel(Customer $customer): string
    {
        $meta = is_array($customer->meta) ? $customer->meta : [];
        $raw = \App\Support\LegacyPortalSource::rawSnapshot($meta);

        return trim(
            (string) ($raw['Status'] ?? '—')
            .' / '
            .(string) ($raw['ShortStatus'] ?? '—')
            .(filter_var($raw['Disabled'] ?? false, FILTER_VALIDATE_BOOLEAN) ? ' (disabled)' : ''),
        );
    }
}
