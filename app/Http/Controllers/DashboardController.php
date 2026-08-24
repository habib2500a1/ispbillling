<?php

namespace App\Http\Controllers;

use App\Models\BillingInfo;
use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Models\HotspotSale;
use App\Models\IspExpense;
use App\Models\PPPSecrets;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use App\Services\Ai\OpsInsightsService;
use App\Services\Dashboard\DashboardOpsService;
use App\Services\Noc\NocOverviewService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (auth()->check() && auth()->user()->hasRole('Reseller')) {
            return redirect()->route('reseller.dashboard');
        }

        $results = [];

        $currentYear = Carbon::now()->year;
        $previousYear = Carbon::now()->subYear()->year;

        // Group status counts to execute a single query instead of 6 count queries
        $statusCounts = CustomersInfo::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $recentCount = CustomersInfo::whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->count();

        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $monthLabel = Carbon::now()->format('F Y');

        $onlinePppIds = PPPSecrets::query()
            ->where('status', '!=', 'removed')
            ->whereNotNull('uptime')
            ->pluck('id');
        $offlinePppIds = PPPSecrets::query()
            ->where('status', '!=', 'removed')
            ->whereNull('uptime')
            ->pluck('id');

        $clientSummary = [
            'total' => array_sum($statusCounts),
            'active' => $statusCounts['active'] ?? 0,
            'online' => CustomersInfo::whereIn('ppp_user_id', $onlinePppIds)->count(),
            'offline' => CustomersInfo::whereIn('ppp_user_id', $offlinePppIds)->count(),
            'inactive' => ($statusCounts['inactive'] ?? 0) + ($statusCounts['disable'] ?? 0),
            'inactive_due' => CustomersInfo::query()
                ->whereIn('status', ['inactive', 'disable'])
                ->whereHas('billing', fn ($q) => $q->where('due_amount', '>', 0))
                ->count(),
            'expired' => CustomersInfo::query()
                ->whereHas('billing', fn ($q) => $q->whereDate('auto_disable_date', '<', $today)->where('due_amount', '>', 0))
                ->whereNotIn('status', ['free'])
                ->count(),
            'joined_month' => $recentCount,
            'joined_today' => CustomersInfo::whereDate('created_at', $today)->count(),
            'expired_today' => CustomersInfo::query()
                ->whereHas('billing', fn ($q) => $q->whereDate('auto_disable_date', $today))
                ->count(),
            'inactive_today' => CustomersInfo::query()
                ->whereIn('status', ['inactive', 'disable'])
                ->whereDate('updated_at', $today)
                ->count(),
        ];

        $monthCollection = (float) CollectionSummary::whereBetween('collection_date', [$monthStart, $monthEnd])->sum('collection_amount');
        $monthDue = (float) BillingInfo::join('customers_infos', 'billing_infos.customer_bill_unique_id', '=', 'customers_infos.customer_unique_id')
            ->whereNull('customers_infos.deleted_at')
            ->where('customers_infos.status', '!=', 'inactive')
            ->sum('billing_infos.due_amount');
        $monthDue = max(0, $monthDue);

        $monthBill = (float) BillingInfo::join('customers_infos', 'billing_infos.customer_bill_unique_id', '=', 'customers_infos.customer_unique_id')
            ->whereNull('customers_infos.deleted_at')
            ->whereNotIn('customers_infos.status', ['inactive', 'free'])
            ->selectRaw('SUM(COALESCE(billing_infos.monthly_rent, 0) + COALESCE(billing_infos.additional_charge, 0) + COALESCE(billing_infos.vat, 0)) as t')
            ->value('t');
        // Prefer billed + outstanding picture when monthly rent sum is empty
        if ($monthBill <= 0) {
            $monthBill = $monthCollection + $monthDue;
        }

        $monthDiscount = (float) BillingInfo::join('customers_infos', 'billing_infos.customer_bill_unique_id', '=', 'customers_infos.customer_unique_id')
            ->whereNull('customers_infos.deleted_at')
            ->sum('billing_infos.discount');
        $monthExpense = 0.0;
        if (Schema::hasTable('isp_expenses')) {
            $monthExpense = (float) IspExpense::whereBetween('expense_date', [$monthStart, $monthEnd])->sum('amount');
        }

        $collectDueTotal = max(0.01, $monthCollection + $monthDue);
        $financialSummary = [
            'month_label' => $monthLabel,
            'bill' => $monthBill,
            'today_collection' => (float) CollectionSummary::whereDate('collection_date', $today)->sum('collection_amount'),
            'collection' => $monthCollection,
            'due' => $monthDue,
            'discount' => $monthDiscount,
            'expense' => $monthExpense,
            'collection_pct' => round(($monthCollection / $collectDueTotal) * 100, 1),
            'due_pct' => round(($monthDue / $collectDueTotal) * 100, 1),
        ];

        $lineGrowth = ['labels' => [], 'new' => [], 'monthly' => []];
        for ($i = 5; $i >= 0; $i--) {
            $m = Carbon::now()->subMonths($i);
            $lineGrowth['labels'][] = $m->format('M Y');
            $lineGrowth['new'][] = CustomersInfo::whereYear('created_at', $m->year)
                ->whereMonth('created_at', $m->month)
                ->count();
            $lineGrowth['monthly'][] = CustomersInfo::where('created_at', '<=', $m->copy()->endOfMonth())->count();
        }

        $customersData = [
            'total' => array_sum($statusCounts),
            'active' => $statusCounts['active'] ?? 0,
            'pending' => $statusCounts['pending'] ?? 0,
            'free' => $statusCounts['free'] ?? 0,
            'temporary_disable' => $statusCounts['disable'] ?? 0,
            'inactive' => $statusCounts['inactive'] ?? 0,
            'recent' => $recentCount,
        ];

        // Optimized query: sum columns directly on the database using Eloquent model
        $billingStats = BillingInfo::join('customers_infos', 'billing_infos.customer_bill_unique_id', '=', 'customers_infos.customer_unique_id')
            ->whereNull('customers_infos.deleted_at')
            ->selectRaw("
                SUM(billing_infos.monthly_rent) as monthly_rent,
                SUM(billing_infos.advance) as advance,
                SUM(billing_infos.paid_amount) as paid_amount,
                SUM(CASE WHEN customers_infos.status != 'inactive' THEN billing_infos.previous_due ELSE 0 END) as previous_due_active,
                SUM(CASE WHEN customers_infos.status != 'inactive' THEN billing_infos.due_amount ELSE 0 END) as due_amount_active
            ")
            ->first();

        // Per-status billing breakdown for chart
        $statusBillingRaw = BillingInfo::join('customers_infos', 'billing_infos.customer_bill_unique_id', '=', 'customers_infos.customer_unique_id')
            ->whereNull('customers_infos.deleted_at')
            ->whereIn('customers_infos.status', ['active', 'free', 'inactive', 'pending'])
            ->selectRaw('
                customers_infos.status,
                SUM(billing_infos.monthly_rent) as monthly_rent,
                SUM(billing_infos.advance) as advance,
                SUM(billing_infos.due_amount) as due_amount
            ')
            ->groupBy('customers_infos.status')
            ->get()
            ->keyBy('status');

        $statuses = ['active', 'free', 'inactive', 'pending'];
        $billingByStatus = [];
        foreach ($statuses as $s) {
            $row = $statusBillingRaw->get($s);
            $billingByStatus[$s] = [
                'monthly_rent' => (float) ($row->monthly_rent ?? 0),
                'advance' => (float) ($row->advance ?? 0),
                'due_amount' => (float) ($row->due_amount ?? 0),
            ];
        }

        $billInformationData = [
            'monthly_rent' => (float) ($billingStats->monthly_rent ?? 0),
            'previous_due' => -1 * (float) ($billingStats->previous_due_active ?? 0),
            'advance' => (float) ($billingStats->advance ?? 0),
            'paid_amount' => (float) ($billingStats->paid_amount ?? 0),
            'today_paid_amount' => CollectionSummary::whereDate('collection_date', Carbon::today())->sum('collection_amount'),
            'hotspot_total' => HotspotSale::sum('amount'),
            'hotspot_today' => HotspotSale::whereDate('sale_date', Carbon::today())->sum('amount'),
            'due_amount' => -1 * (float) ($billingStats->due_amount_active ?? 0),
            'by_status' => $billingByStatus,
        ];

        // Reseller data for admin
        $resellerStatusCounts = Reseller::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $resellerData = [
            'total_resellers' => array_sum($resellerStatusCounts),
            'active_resellers' => $resellerStatusCounts['active'] ?? 0,
            'suspended_resellers' => $resellerStatusCounts['suspended'] ?? 0,
            'total_balance' => Reseller::sum('balance'),
            'total_customers' => CustomersInfo::whereNotNull('reseller_id')->count(),
            'active_customers' => CustomersInfo::whereNotNull('reseller_id')->where('status', 'active')->count(),
            'pending_customers' => CustomersInfo::whereNotNull('reseller_id')->where('status', 'pending')->count(),
            'total_commission' => ResellerCommission::sum('amount'),
        ];

        $isSqlite = config('database.default') === 'sqlite';
        $monthExpr = $isSqlite ? "CAST(strftime('%m', collection_date) AS INTEGER)" : 'MONTH(collection_date)';
        $hotspotMonthExpr = $isSqlite ? "CAST(strftime('%m', sale_date) AS INTEGER)" : 'MONTH(sale_date)';

        // Pre-group yearly collections/hotspots by month to avoid looping 48 SQL queries
        $collectionsPreviousYear = CollectionSummary::whereYear('collection_date', $previousYear)
            ->selectRaw("{$monthExpr} as month, SUM(collection_amount) as total")
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();

        $collectionsCurrentYear = CollectionSummary::whereYear('collection_date', $currentYear)
            ->selectRaw("{$monthExpr} as month, SUM(collection_amount) as total")
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();

        $hotspotPreviousYear = HotspotSale::whereYear('sale_date', $previousYear)
            ->selectRaw("{$hotspotMonthExpr} as month, SUM(amount) as total")
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();

        $hotspotCurrentYear = HotspotSale::whereYear('sale_date', $currentYear)
            ->selectRaw("{$hotspotMonthExpr} as month, SUM(amount) as total")
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();

        // Loop through each month using pre-fetched array maps
        for ($month = 1; $month <= 12; $month++) {
            $cashflowPreviousYear = ($collectionsPreviousYear[$month] ?? 0) + ($hotspotPreviousYear[$month] ?? 0);
            $incomeCurrentYear = ($collectionsCurrentYear[$month] ?? 0) + ($hotspotCurrentYear[$month] ?? 0);
            $revenueDifference = $incomeCurrentYear - $cashflowPreviousYear;

            $results[$month] = [
                'cashflow_previous_year' => (float) $cashflowPreviousYear,
                'income_current_year' => (float) $incomeCurrentYear,
                'revenue_difference' => (float) $revenueDifference,
            ];
        }

        try {
            $systemOverview = app(MikrotikController::class)->systemOverview();
        } catch (\Exception $e) {
            $systemOverview = [];
        }

        $nocPayload = app(NocOverviewService::class)->payload();
        $opticalData = $nocPayload['optical'];
        $networkQuick = $nocPayload['network'];
        $opsData = app(DashboardOpsService::class)->snapshot();

        try {
            $insights = app(OpsInsightsService::class)->payload();
            $opsData['insights_critical'] = (int) ($insights['counts']['critical'] ?? 0);
            $opsData['insights_high'] = (int) ($insights['counts']['high'] ?? 0);
            $opsData['insights_total'] = count($insights['items'] ?? []);
        } catch (\Throwable) {
            $opsData['insights_critical'] = 0;
            $opsData['insights_high'] = 0;
            $opsData['insights_total'] = 0;
        }

        // Calculate total cashflow, income, and revenue difference for the year
        return view('dashboard', compact(
            'results',
            'customersData',
            'billInformationData',
            'systemOverview',
            'resellerData',
            'opticalData',
            'networkQuick',
            'opsData',
            'clientSummary',
            'financialSummary',
            'lineGrowth'
        ));
    }
}
