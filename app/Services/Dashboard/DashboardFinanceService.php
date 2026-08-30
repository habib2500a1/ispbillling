<?php

namespace App\Services\Dashboard;

use App\Models\BillingInfo;
use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Models\HotspotSale;
use App\Models\IspExpense;
use App\Models\PackageList;
use App\Models\PaymentSummary;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboard financial KPIs: generated bill, collection, due, percentages.
 */
final class DashboardFinanceService
{
    /**
     * @return array{
     *   month_label: string,
     *   bill: float,
     *   today_collection: float,
     *   collection: float,
     *   due: float,
     *   discount: float,
     *   expense: float,
     *   collection_pct: float,
     *   due_pct: float,
     *   bills_generated: int,
     *   billable_customers: int,
     *   bill_source: string
     * }
     */
    public function summary(?Carbon $when = null): array
    {
        $when = $when ?: Carbon::now();
        $today = $when->copy()->startOfDay();
        $monthStart = $when->copy()->startOfMonth();
        $monthEnd = $when->copy()->endOfMonth();
        $monthLabel = $when->format('F Y');

        $monthCollection = (float) CollectionSummary::query()
            ->whereBetween('collection_date', [$monthStart, $monthEnd])
            ->sum('collection_amount');

        $todayCollection = (float) CollectionSummary::query()
            ->whereDate('collection_date', $today)
            ->sum('collection_amount');

        if (Schema::hasTable('hotspot_sales')) {
            $monthCollection += (float) HotspotSale::query()
                ->whereBetween('sale_date', [$monthStart, $monthEnd])
                ->sum('amount');
            $todayCollection += (float) HotspotSale::query()
                ->whereDate('sale_date', $today)
                ->sum('amount');
        }

        $monthDue = (float) BillingInfo::query()
            ->join('customers_infos', 'billing_infos.customer_bill_unique_id', '=', 'customers_infos.customer_unique_id')
            ->whereNull('customers_infos.deleted_at')
            ->where('customers_infos.status', '!=', 'inactive')
            ->sum('billing_infos.due_amount');
        $monthDue = max(0, $monthDue);

        $recurringBill = (float) BillingInfo::query()
            ->join('customers_infos', 'billing_infos.customer_bill_unique_id', '=', 'customers_infos.customer_unique_id')
            ->whereNull('customers_infos.deleted_at')
            ->whereNotIn('customers_infos.status', ['inactive', 'free'])
            ->selectRaw('SUM(COALESCE(billing_infos.monthly_rent, 0) + COALESCE(billing_infos.additional_charge, 0) + COALESCE(billing_infos.vat, 0)) as t')
            ->value('t');

        $generatedBill = 0.0;
        $billsGenerated = 0;
        if (Schema::hasTable('payment_summaries')) {
            $generated = PaymentSummary::query()
                ->whereYear('summary_date', $when->year)
                ->whereMonth('summary_date', $when->month)
                ->selectRaw('COUNT(*) as n, SUM(COALESCE(monthly_rent, 0) + COALESCE(additional_charge, 0) + COALESCE(vat, 0)) as t')
                ->first();
            $billsGenerated = (int) ($generated->n ?? 0);
            $generatedBill = (float) ($generated->t ?? 0);
        }

        $packageBill = 0.0;
        if ($recurringBill <= 0 && Schema::hasTable('package_lists')) {
            $packageBill = (float) CustomersInfo::query()
                ->whereNull('deleted_at')
                ->whereNotIn('status', ['inactive', 'free'])
                ->whereNotNull('package_id')
                ->join('package_lists', 'package_lists.id', '=', 'customers_infos.package_id')
                ->sum('package_lists.price');
        }

        $billableCustomers = (int) CustomersInfo::query()
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['inactive', 'free'])
            ->count();

        $bill = $recurringBill;
        $source = 'monthly_rent';
        if ($bill <= 0 && $generatedBill > 0) {
            $bill = $generatedBill;
            $source = 'payment_summary';
        }
        if ($bill <= 0 && $packageBill > 0) {
            $bill = $packageBill;
            $source = 'package_price';
        }
        if ($bill <= 0 && ($monthCollection + $monthDue) > 0) {
            $bill = $monthCollection + $monthDue;
            $source = 'collection_plus_due';
        }

        $monthDiscount = (float) BillingInfo::query()
            ->join('customers_infos', 'billing_infos.customer_bill_unique_id', '=', 'customers_infos.customer_unique_id')
            ->whereNull('customers_infos.deleted_at')
            ->sum('billing_infos.discount');

        $monthExpense = 0.0;
        if (Schema::hasTable('isp_expenses')) {
            $monthExpense = (float) IspExpense::query()
                ->whereBetween('expense_date', [$monthStart, $monthEnd])
                ->sum('amount');
        }

        $collectDueTotal = max(0.01, $monthCollection + $monthDue);

        return [
            'month_label' => $monthLabel,
            'bill' => round($bill, 2),
            'today_collection' => round($todayCollection, 2),
            'collection' => round($monthCollection, 2),
            'due' => round($monthDue, 2),
            'discount' => round($monthDiscount, 2),
            'expense' => round($monthExpense, 2),
            'collection_pct' => round(($monthCollection / $collectDueTotal) * 100, 1),
            'due_pct' => round(($monthDue / $collectDueTotal) * 100, 1),
            'bills_generated' => $billsGenerated,
            'billable_customers' => $billableCustomers,
            'bill_source' => $source,
        ];
    }

    public function recentPayments(int $limit = 12)
    {
        return CollectionSummary::query()
            ->with(['customer.pppUser'])
            ->orderByDesc('collection_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
