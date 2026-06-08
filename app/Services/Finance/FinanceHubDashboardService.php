<?php

namespace App\Services\Finance;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PendingGatewayPayment;
use App\Models\StaffExpense;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Services\Accounts\AccountsDashboardService;
use App\Services\Billing\BillingOpsMetricsService;
use App\Services\Dashboard\BillingDashboardMetricsService;
use App\Services\Reports\AnalyticsReportService;
use App\Support\PerformanceSettings;
use App\Support\SafeCache;
use App\Support\TenantResolver;
use Carbon\Carbon;

/**
 * Read-only finance command center aggregator (no calculation changes).
 */
final class FinanceHubDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $tenantId = TenantResolver::currentTenantId() ?? 1;
        $cacheKey = 'finance_hub:snapshot:'.$tenantId;

        return SafeCache::remember($cacheKey, PerformanceSettings::hubCacheSeconds(), fn () => $this->build($tenantId));
    }

    /**
     * @return array<string, mixed>
     */
    private function build(int $tenantId): array
    {
        $from = now()->startOfMonth();
        $to = now()->endOfMonth();
        $today = now()->startOfDay();

        $accounts = app(AccountsDashboardService::class)->stats($from, $to);
        $billingPayload = app(BillingDashboardMetricsService::class)->payload($tenantId);
        $billingKpis = collect($billingPayload['kpis'] ?? [])->keyBy('key');
        $ops = app(BillingOpsMetricsService::class)->snapshot($tenantId);
        $analytics = app(AnalyticsReportService::class);
        $summary = $analytics->summary($from, $to, $tenantId);

        $todayCollection = (float) Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('paid_at', '>=', $today)
            ->sum('amount');

        $monthCollection = (float) ($accounts['collections'] ?? $billingKpis->get('collected')['value'] ?? 0);
        $totalRevenue = (float) ($billingKpis->get('monthly_bill')['value'] ?? $accounts['income'] ?? 0);
        $dueCollection = (float) ($billingKpis->get('total_due')['value'] ?? $ops['outstanding'] ?? 0);
        $overdueCollection = (float) (($ops['aging']['1_30']['amount'] ?? 0)
            + ($ops['aging']['31_60']['amount'] ?? 0)
            + ($ops['aging']['60_plus']['amount'] ?? 0));

        $mfsPending = (float) PendingGatewayPayment::query()
            ->where('tenant_id', $tenantId)
            ->where('status', PendingGatewayPayment::STATUS_PENDING)
            ->sum('amount');

        $cashFlow = (float) ($accounts['cashbook_in'] ?? 0) - (float) ($accounts['cashbook_out'] ?? 0);

        $monthInvoiced = (float) ($summary['invoiced'] ?? 0);
        $collectionEfficiency = $monthInvoiced > 0
            ? round(($monthCollection / $monthInvoiced) * 100, 1)
            : 0.0;

        $activeSubs = max(1, (int) ($summary['active_subscribers'] ?? 0));
        $lifetimeCollected = (float) Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->sum('amount');
        $clvProxy = round($lifetimeCollected / $activeSubs, 2);

        return [
            'kpis' => [
                'total_revenue' => round($totalRevenue, 2),
                'today_collection' => round($todayCollection, 2),
                'monthly_collection' => round($monthCollection, 2),
                'due_collection' => round($dueCollection, 2),
                'overdue_collection' => round($overdueCollection, 2),
                'total_expenses' => round((float) ($accounts['expenses'] ?? 0), 2),
                'net_profit' => round((float) ($accounts['net_profit'] ?? 0), 2),
                'cash_flow' => round($cashFlow, 2),
                'bank_balance' => round((float) ($accounts['bank_balance'] ?? 0), 2),
                'mobile_banking' => round($mfsPending + (float) ($accounts['collector_cash'] ?? 0), 2),
                'cash_balance' => round((float) ($accounts['cash_balance'] ?? 0), 2),
                'collection_efficiency' => $collectionEfficiency,
            ],
            'period_label' => $from->format('F Y'),
            'profit_margin' => $this->profitMargin($accounts),
            'income_pct' => $this->incomePct($accounts),
            'ops' => $ops,
            'accounts' => $accounts,
            'billing_growth' => $billingPayload['growth'] ?? [],
            'isp_analytics' => [
                'zone_revenue' => array_slice($analytics->zoneCollectionReport($from, $to, $tenantId), 0, 8),
                'package_revenue' => array_slice($analytics->packagePopularity($tenantId), 0, 8),
                'area_revenue' => array_slice($analytics->areaWiseReport($tenantId), 0, 8),
                'clv_proxy' => $clvProxy,
                'collection_efficiency' => $collectionEfficiency,
            ],
            'pending_expenses' => $this->pendingExpenses($tenantId),
            'recent_payments' => $this->recentPayments($tenantId, 10),
            'report_links' => $this->reportLinks(),
            'refreshed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query, int $limit = 25): array
    {
        $q = trim($query);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $tenantId = TenantResolver::currentTenantId();
        $likeOp = Customer::query()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $results = [];

        Customer::query()
            ->when($tenantId, fn ($qb) => $qb->where('tenant_id', $tenantId))
            ->where(function ($qb) use ($q, $likeOp): void {
                $qb->where('name', $likeOp, "%{$q}%")
                    ->orWhere('customer_code', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            })
            ->limit(6)
            ->get(['id', 'name', 'customer_code'])
            ->each(function (Customer $c) use (&$results): void {
                $results[] = [
                    'type' => 'customer',
                    'label' => $c->name,
                    'meta' => $c->customer_code,
                    'url' => \App\Filament\Resources\CustomerResource::getUrl('view', ['record' => $c->id]),
                ];
            });

        Invoice::query()
            ->when($tenantId, fn ($qb) => $qb->where('tenant_id', $tenantId))
            ->where('invoice_number', 'like', "%{$q}%")
            ->orderByDesc('id')
            ->limit(6)
            ->get(['id', 'invoice_number', 'total', 'status'])
            ->each(function (Invoice $inv) use (&$results): void {
                $results[] = [
                    'type' => 'invoice',
                    'label' => $inv->invoice_number,
                    'meta' => $inv->status.' · '.number_format((float) $inv->total, 0).' BDT',
                    'url' => \App\Filament\Resources\InvoiceResource::getUrl('edit', ['record' => $inv->id]),
                ];
            });

        Payment::query()
            ->when($tenantId, fn ($qb) => $qb->where('tenant_id', $tenantId))
            ->where(function ($qb) use ($q): void {
                $qb->where('receipt_number', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%");
            })
            ->orderByDesc('id')
            ->limit(6)
            ->get(['id', 'receipt_number', 'amount', 'status'])
            ->each(function (Payment $pay) use (&$results): void {
                $results[] = [
                    'type' => 'payment',
                    'label' => $pay->receipt_number ?: 'Payment #'.$pay->id,
                    'meta' => $pay->status.' · '.number_format((float) $pay->amount, 0).' BDT',
                    'url' => \App\Filament\Resources\PaymentResource::getUrl('edit', ['record' => $pay->id]),
                ];
            });

        Vendor::query()
            ->when($tenantId, fn ($qb) => $qb->where('tenant_id', $tenantId))
            ->where('name', $likeOp, "%{$q}%")
            ->limit(5)
            ->get(['id', 'name'])
            ->each(function (Vendor $v) use (&$results): void {
                $results[] = [
                    'type' => 'vendor',
                    'label' => $v->name,
                    'meta' => 'Vendor',
                    'url' => \App\Filament\Resources\VendorResource::getUrl('edit', ['record' => $v->id]),
                ];
            });

        ChartOfAccount::query()
            ->when($tenantId, fn ($qb) => $qb->where('tenant_id', $tenantId))
            ->where(function ($qb) use ($q, $likeOp): void {
                $qb->where('name', $likeOp, "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            })
            ->limit(5)
            ->get(['id', 'name', 'code'])
            ->each(function (ChartOfAccount $acc) use (&$results): void {
                $results[] = [
                    'type' => 'account',
                    'label' => $acc->name,
                    'meta' => $acc->code,
                    'url' => \App\Filament\Resources\ChartOfAccountResource::getUrl('edit', ['record' => $acc->id]),
                ];
            });

        VendorPayment::query()
            ->when($tenantId, fn ($qb) => $qb->where('tenant_id', $tenantId))
            ->where('payee_name', $likeOp, "%{$q}%")
            ->orderByDesc('id')
            ->limit(4)
            ->get(['id', 'payee_name', 'amount'])
            ->each(function (VendorPayment $exp) use (&$results): void {
                $results[] = [
                    'type' => 'expense',
                    'label' => $exp->payee_name ?: 'Expense #'.$exp->id,
                    'meta' => number_format((float) $exp->amount, 0).' BDT',
                    'url' => \App\Filament\Resources\VendorPaymentResource::getUrl('edit', ['record' => $exp->id]),
                ];
            });

        StaffExpense::query()
            ->when($tenantId, fn ($qb) => $qb->where('tenant_id', $tenantId))
            ->where(function ($qb) use ($q, $likeOp): void {
                $qb->where('description', $likeOp, "%{$q}%");
            })
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->limit(4)
            ->get(['id', 'description', 'amount'])
            ->each(function (StaffExpense $e) use (&$results): void {
                $results[] = [
                    'type' => 'expense',
                    'label' => $e->description ?: 'Staff expense #'.$e->id,
                    'meta' => number_format((float) $e->amount, 0).' BDT · pending',
                    'url' => \App\Filament\Resources\StaffExpenseResource::getUrl('view', ['record' => $e->id]),
                ];
            });

        return array_slice($results, 0, $limit);
    }

    /**
     * @param  array<string, mixed>  $accounts
     */
    private function profitMargin(array $accounts): float
    {
        $income = (float) ($accounts['income'] ?? 0);
        $profit = (float) ($accounts['net_profit'] ?? 0);

        return $income > 0 ? round(($profit / $income) * 100, 1) : 0.0;
    }

    /**
     * @param  array<string, mixed>  $accounts
     */
    private function incomePct(array $accounts): int
    {
        $income = (float) ($accounts['income'] ?? 0);
        $expenses = (float) ($accounts['expenses'] ?? 0);

        return $income + $expenses > 0 ? (int) round(($income / ($income + $expenses)) * 100) : 50;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingExpenses(int $tenantId): array
    {
        $items = [];

        StaffExpense::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'amount', 'description', 'created_at'])
            ->each(function (StaffExpense $e) use (&$items): void {
                $items[] = [
                    'type' => 'staff',
                    'label' => $e->description ?: 'Staff expense',
                    'amount' => (float) $e->amount,
                    'url' => \App\Filament\Resources\StaffExpenseResource::getUrl('view', ['record' => $e->id]),
                ];
            });

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentPayments(int $tenantId, int $limit): array
    {
        return Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->with('customer:id,name,customer_code')
            ->orderByDesc('paid_at')
            ->limit($limit)
            ->get()
            ->map(fn (Payment $p) => [
                'receipt' => $p->receipt_number ?: '#'.$p->id,
                'customer' => $p->customer?->name,
                'amount' => (float) $p->amount,
                'method' => $p->method,
                'at' => $p->paid_at?->format('M j, H:i'),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reportLinks(): array
    {
        return [
            ['label' => 'Profit & Loss', 'url' => \App\Filament\Pages\FinancialReports::getUrl(), 'icon' => 'chart-pie'],
            ['label' => 'Balance sheet', 'url' => \App\Filament\Pages\FinancialReports::getUrl(), 'icon' => 'scale'],
            ['label' => 'Cash flow statement', 'url' => \App\Filament\Pages\FinancialReports::getUrl(), 'icon' => 'arrow-path-rounded-square'],
            ['label' => 'Revenue report', 'url' => \App\Filament\Pages\BillingOverview::getUrl(), 'icon' => 'banknotes'],
            ['label' => 'Expense report', 'url' => \App\Filament\Pages\AccountsExpensesPage::getUrl(), 'icon' => 'arrow-trending-down'],
            ['label' => 'Collection report', 'url' => \App\Filament\Pages\PaymentsReport::getUrl(), 'icon' => 'currency-bangladeshi'],
            ['label' => 'Cashbook summary', 'url' => \App\Filament\Pages\FinancialReports::getUrl(), 'icon' => 'wallet'],
            ['label' => 'Fund flow', 'url' => \App\Filament\Pages\BillingFundFlowReport::getUrl(), 'icon' => 'arrow-path'],
            ['label' => 'Due report', 'url' => \App\Filament\Pages\DueReportPage::getUrl(), 'icon' => 'exclamation-circle'],
            ['label' => 'Analytics', 'url' => \App\Filament\Pages\AnalyticsReports::getUrl(), 'icon' => 'chart-bar'],
            ['label' => 'Gateway reconcile', 'url' => \App\Filament\Pages\GatewayReconciliationReport::getUrl(), 'icon' => 'signal'],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function glCounts(int $tenantId): array
    {
        return [
            'accounts' => ChartOfAccount::query()->where('tenant_id', $tenantId)->count(),
            'journals' => JournalEntry::query()->where('tenant_id', $tenantId)->whereMonth('entry_date', now()->month)->count(),
            'banks' => BankAccount::query()->where('tenant_id', $tenantId)->where('is_active', true)->count(),
            'vendors' => Vendor::query()->where('tenant_id', $tenantId)->where('is_active', true)->count(),
        ];
    }
}
