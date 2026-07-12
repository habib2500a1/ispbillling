<?php

namespace App\Services\Accounts;

use App\Models\BillingInfo;
use App\Models\CollectionSummary;
use App\Models\HotspotSale;
use App\Models\IspExpense;
use App\Models\Reseller;
use App\Models\ResellerCommission;
use Carbon\Carbon;

/**
 * Accounts / Finance hub for Code Pagol.
 * Read-only aggregates from existing collections, hotspot sales, expenses, dues.
 * No schema change — mirrors ispbilling AccountsDashboard intent with local models.
 */
final class AccountsHubService
{
    /**
     * @return array<string, mixed>
     */
    public function payload(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = ($from ?? now()->startOfMonth())->copy()->startOfDay();
        $to = ($to ?? now()->endOfMonth())->copy()->endOfDay();

        $collectionRevenue = (float) CollectionSummary::query()
            ->whereBetween('collection_date', [$from->toDateString(), $to->toDateString()])
            ->sum('collection_amount');

        $hotspotRevenue = (float) HotspotSale::query()
            ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $totalIncome = $collectionRevenue + $hotspotRevenue;

        $expensesByCategory = IspExpense::query()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as cnt')
            ->groupBy('category')
            ->get()
            ->map(fn ($row) => [
                'category' => (string) $row->category,
                'label' => IspExpense::$categories[$row->category] ?? ucfirst((string) $row->category),
                'total' => round((float) $row->total, 2),
                'count' => (int) $row->cnt,
                'color' => IspExpense::$categoryColors[$row->category] ?? 'secondary',
            ])
            ->values()
            ->all();

        $totalExpenses = round(array_sum(array_column($expensesByCategory, 'total')), 2);

        $resellerCommissions = (float) ResellerCommission::query()
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $netProfit = round($totalIncome - $resellerCommissions - $totalExpenses, 2);

        $collectionCount = (int) CollectionSummary::query()
            ->whereBetween('collection_date', [$from->toDateString(), $to->toDateString()])
            ->count();

        $expenseCount = (int) IspExpense::query()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->count();

        $todayCollected = (float) CollectionSummary::query()
            ->whereDate('collection_date', Carbon::today())
            ->sum('collection_amount');

        $dues = BillingInfo::query()
            ->join('customers_infos', 'customers_infos.customer_unique_id', '=', 'billing_infos.customer_bill_unique_id')
            ->whereNull('customers_infos.deleted_at')
            ->whereNotIn('customers_infos.status', ['deleted'])
            ->selectRaw("
                SUM(CASE WHEN customers_infos.status != 'inactive' THEN billing_infos.due_amount ELSE 0 END) as due_active,
                SUM(CASE WHEN customers_infos.status != 'inactive' THEN billing_infos.previous_due ELSE 0 END) as previous_due_active,
                SUM(CASE WHEN customers_infos.status != 'inactive' THEN billing_infos.monthly_rent ELSE 0 END) as monthly_rent,
                COUNT(CASE WHEN billing_infos.due_amount > 0 AND customers_infos.status IN ('active','pending','disable') THEN 1 END) as due_customers
            ")
            ->first();

        $resellerBalance = (float) Reseller::query()->sum('balance');

        $paymentBreakdown = CollectionSummary::query()
            ->whereBetween('collection_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw("COALESCE(NULLIF(payment_method,''),'unknown') as method, SUM(collection_amount) as total, COUNT(*) as cnt")
            ->groupBy('method')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'method' => (string) $row->method,
                'total' => round((float) $row->total, 2),
                'count' => (int) $row->cnt,
            ])
            ->all();

        $recentCollections = CollectionSummary::query()
            ->with('customer:id,customer_unique_id,customer_name,mobile')
            ->whereBetween('collection_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('collection_date')
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(fn (CollectionSummary $c) => [
                'id' => $c->id,
                'date' => optional($c->collection_date)->format('Y-m-d') ?? (string) $c->collection_date,
                'amount' => round((float) $c->collection_amount, 2),
                'method' => $c->payment_method ?: '—',
                'customer' => $c->customer?->customer_name ?? $c->customer_collection_unique_id,
                'uid' => $c->customer_collection_unique_id,
            ])
            ->all();

        $recentExpenses = IspExpense::query()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(fn (IspExpense $e) => [
                'id' => $e->id,
                'date' => $e->expense_date?->format('Y-m-d'),
                'title' => $e->title,
                'category' => $e->category_label,
                'color' => $e->category_color,
                'amount' => round((float) $e->amount, 2),
            ])
            ->all();

        $trend = $this->monthlyTrend(12);

        return [
            'updated_at' => now()->toIso8601String(),
            'period_meta' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => $from->format('d M Y').' → '.$to->format('d M Y'),
            ],
            'kpis' => [
                'income' => round($totalIncome, 2),
                'collections' => round($collectionRevenue, 2),
                'hotspot' => round($hotspotRevenue, 2),
                'expenses' => $totalExpenses,
                'commissions' => round($resellerCommissions, 2),
                'net_profit' => $netProfit,
                'today_collected' => round($todayCollected, 2),
                'due_active' => round((float) ($dues->due_active ?? 0), 2),
                'previous_due_active' => round((float) ($dues->previous_due_active ?? 0), 2),
                'monthly_rent' => round((float) ($dues->monthly_rent ?? 0), 2),
                'due_customers' => (int) ($dues->due_customers ?? 0),
                'reseller_balance' => round($resellerBalance, 2),
                'collection_count' => $collectionCount,
                'expense_count' => $expenseCount,
            ],
            'expenses_by_category' => $expensesByCategory,
            'payment_breakdown' => $paymentBreakdown,
            'recent_collections' => $recentCollections,
            'recent_expenses' => $recentExpenses,
            'trend' => $trend,
        ];
    }

    /**
     * Last N months revenue vs expense (incl. commissions).
     *
     * @return list<array{label: string, revenue: float, expense: float, net: float}>
     */
    private function monthlyTrend(int $months = 12): array
    {
        $start = Carbon::now()->subMonths($months - 1)->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        $collections = CollectionSummary::query()
            ->whereBetween('collection_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("DATE_FORMAT(collection_date, '%Y-%m') as ym, SUM(collection_amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $hotspot = HotspotSale::query()
            ->whereBetween('sale_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("DATE_FORMAT(sale_date, '%Y-%m') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $expenses = IspExpense::query()
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("DATE_FORMAT(expense_date, '%Y-%m') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $commissions = ResellerCommission::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $out = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $d = Carbon::now()->subMonths($i);
            $key = $d->format('Y-m');
            $revenue = (float) ($collections[$key] ?? 0) + (float) ($hotspot[$key] ?? 0);
            $expense = (float) ($expenses[$key] ?? 0) + (float) ($commissions[$key] ?? 0);
            $out[] = [
                'label' => $d->format('M Y'),
                'revenue' => round($revenue, 2),
                'expense' => round($expense, 2),
                'net' => round($revenue - $expense, 2),
            ];
        }

        return $out;
    }
}
