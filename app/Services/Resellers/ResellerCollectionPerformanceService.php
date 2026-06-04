<?php

namespace App\Services\Resellers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Reseller;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class ResellerCollectionPerformanceService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function partnerRows(?Carbon $from = null, ?Carbon $to = null, ?int $tenantId = null): array
    {
        $from ??= now()->startOfMonth();
        $to ??= now()->endOfDay();

        $query = Reseller::query()->withoutGlobalScopes()->orderBy('name');
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get()->map(function (Reseller $reseller) use ($from, $to): array {
            $customerIds = $reseller->customers()->pluck('id');

            $due = 0.0;
            $collected = 0.0;
            $invoiced = 0.0;
            $active = 0;
            $suspended = 0;

            if ($customerIds->isNotEmpty()) {
                $due = (float) Invoice::query()
                    ->whereIn('customer_id', $customerIds)
                    ->whereIn('status', ['open', 'partial'])
                    ->get()
                    ->sum(fn (Invoice $i): float => max(0, (float) $i->total - (float) $i->amount_paid));

                $collected = (float) Payment::query()
                    ->whereIn('customer_id', $customerIds)
                    ->where('status', 'completed')
                    ->whereBetween('paid_at', [$from, $to])
                    ->sum('amount');

                $invoiced = (float) Invoice::query()
                    ->whereIn('customer_id', $customerIds)
                    ->whereNotIn('status', ['void', 'cancelled'])
                    ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
                    ->sum('total');

                $active = (int) Customer::query()->whereIn('id', $customerIds)->where('status', 'active')->count();
                $suspended = (int) Customer::query()->whereIn('id', $customerIds)->where('status', 'suspended')->count();
            }

            $rate = $invoiced > 0 ? round(($collected / $invoiced) * 100, 1) : 0.0;
            $adminDue = (float) $reseller->admin_receivable_due;
            $limit = (float) $reseller->credit_limit;
            $risk = (float) $reseller->risk_score;

            return [
                'id' => $reseller->id,
                'code' => $reseller->code,
                'name' => $reseller->name,
                'active' => (bool) $reseller->is_active,
                'customers' => $customerIds->count(),
                'active_customers' => $active,
                'suspended_customers' => $suspended,
                'customer_due' => round($due, 2),
                'collected' => round($collected, 2),
                'invoiced' => round($invoiced, 2),
                'collection_rate' => $rate,
                'admin_receivable' => round($adminDue, 2),
                'credit_limit' => round($limit, 2),
                'credit_util' => $limit > 0 ? round(($adminDue / $limit) * 100, 1) : 0,
                'risk_score' => $risk,
            ];
        })->sortByDesc('customer_due')->values()->all();
    }

    /**
     * @return array<string, float|int>
     */
    public function networkTotals(?Carbon $from = null, ?Carbon $to = null): array
    {
        $rows = $this->partnerRows($from, $to);

        return [
            'partners' => count($rows),
            'customer_due' => round(array_sum(array_column($rows, 'customer_due')), 2),
            'admin_receivable' => round(array_sum(array_column($rows, 'admin_receivable')), 2),
            'collected' => round(array_sum(array_column($rows, 'collected')), 2),
            'invoiced' => round(array_sum(array_column($rows, 'invoiced')), 2),
            'avg_collection_rate' => count($rows) > 0
                ? round(array_sum(array_column($rows, 'collection_rate')) / count($rows), 1)
                : 0,
        ];
    }
}
