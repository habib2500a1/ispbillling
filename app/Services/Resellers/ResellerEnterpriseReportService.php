<?php

namespace App\Services\Resellers;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class ResellerEnterpriseReportService
{
    /**
     * @return array<string, mixed>
     */
    public function revenueReport(Reseller $reseller, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->startOfMonth();
        $to ??= now()->endOfDay();

        $commissions = ResellerCommission::query()
            ->where('reseller_id', $reseller->id)
            ->whereBetween('earned_at', [$from, $to]);

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'gross' => (float) (clone $commissions)->sum('gross_amount'),
            'commission' => (float) (clone $commissions)->sum('commission_amount'),
            'parent_share' => (float) (clone $commissions)->sum('parent_share_amount'),
            'pending' => (float) (clone $commissions)->where('status', ResellerCommission::STATUS_PENDING)->sum('commission_amount'),
            'paid' => (float) (clone $commissions)->where('status', ResellerCommission::STATUS_PAID)->sum('commission_amount'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function customerGrowthReport(Reseller $reseller, int $months = 12): array
    {
        $labels = [];
        $values = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $labels[] = $month->format('M Y');
            $values[] = (int) Customer::query()
                ->where('reseller_id', $reseller->id)
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
        }

        return ['labels' => $labels, 'new_customers' => $values];
    }

    /**
     * @return array<string, mixed>
     */
    public function packageSalesReport(Reseller $reseller, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->startOfMonth();
        $to ??= now()->endOfDay();

        $customerIds = $reseller->customers()->pluck('id');
        if ($customerIds->isEmpty()) {
            return ['packages' => []];
        }

        $rows = Payment::query()
            ->whereIn('payments.customer_id', $customerIds)
            ->where('payments.status', 'completed')
            ->whereBetween('payments.paid_at', [$from, $to])
            ->join('customers', 'payments.customer_id', '=', 'customers.id')
            ->join('packages', 'customers.package_id', '=', 'packages.id')
            ->select('packages.name', DB::raw('COUNT(*) as payment_count'), DB::raw('SUM(payments.amount) as total'))
            ->groupBy('packages.id', 'packages.name')
            ->orderByDesc('total')
            ->get();

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'packages' => $rows->map(fn ($r) => [
                'name' => $r->name,
                'payments' => (int) $r->payment_count,
                'total' => (float) $r->total,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function profitLossReport(Reseller $reseller, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->startOfMonth();
        $to ??= now()->endOfDay();

        $revenue = $this->revenueReport($reseller, $from, $to);
        $customerIds = $reseller->customers()->pluck('id');

        $collections = 0.0;
        if ($customerIds->isNotEmpty()) {
            $collections = (float) Payment::query()
                ->whereIn('payments.customer_id', $customerIds)
                ->where('payments.status', 'completed')
                ->whereBetween('payments.paid_at', [$from, $to])
                ->sum('payments.amount');
        }

        $wholesaleDebits = (float) \App\Models\ResellerBalanceTransfer::query()
            ->where('from_reseller_id', $reseller->id)
            ->where('transfer_type', \App\Models\ResellerBalanceTransfer::TYPE_WHOLESALE_DEBIT)
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        return [
            'collections' => $collections,
            'commission_earned' => $revenue['commission'],
            'wholesale_cost' => $wholesaleDebits,
            'estimated_profit' => round($revenue['commission'] - $wholesaleDebits, 2),
        ];
    }
}
