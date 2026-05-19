<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Network\NetworkAccessCoordinator;
use Illuminate\Console\Command;

class EvaluateNetworkAccessCommand extends Command
{
    protected $signature = 'isp:network-evaluate-access {--tenant= : Limit to tenant id}';

    protected $description = 'Re-evaluate network access (suspend/unsuspend) from invoice due dates and customer status.';

    public function handle(NetworkAccessCoordinator $coordinator): int
    {
        $tenantId = $this->option('tenant') !== null && $this->option('tenant') !== ''
            ? (int) $this->option('tenant')
            : null;

        $overdueIds = $this->overdueCustomerIds($tenantId);

        $query = Customer::query()->withoutGlobalScopes();

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        if (config('sync.network_evaluate_only_candidates', true) && config('network.auto_suspend_enabled', false)) {
            $query->where(function ($q) use ($overdueIds): void {
                if ($overdueIds !== []) {
                    $q->whereIn('id', $overdueIds);
                }
                $q->orWhere('network_access_state', 'suspended');
            });
        }

        $processed = 0;
        $query->orderBy('id')->chunkById(200, function ($customers) use ($coordinator, &$processed): void {
            foreach ($customers as $customer) {
                $coordinator->syncCustomer($customer);
                $processed++;
            }
        });

        $this->info("Processed {$processed} customers.".($overdueIds !== [] ? ' ('.count($overdueIds).' with overdue invoices)' : ''));

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function overdueCustomerIds(?int $tenantId): array
    {
        if (! config('network.auto_suspend_enabled', false)) {
            return [];
        }

        $q = Invoice::query()
            ->select('customer_id')
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereRaw('(total - amount_paid) > 0')
            ->whereNotIn('status', ['void', 'cancelled', 'paid', 'draft'])
            ->distinct();

        if ($tenantId !== null) {
            $q->where('tenant_id', $tenantId);
        }

        return $q->pluck('customer_id')->map(fn ($id): int => (int) $id)->all();
    }
}
