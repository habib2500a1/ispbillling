<?php

namespace App\Services\Dashboard;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Services\Accounting\AccountingReportService;
use App\Services\Mobile\StaffBillingKpiResolver;
use App\Support\CustomerBalanceDue;
use App\Support\CustomerStatus;
use App\Support\PaymentType;
use App\Support\TenantResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class BillingDashboardMetricsService
{
    /**
     * @return array<string, mixed>
     */
    public function payload(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        return Cache::remember(
            "billing_dashboard:{$tenantId}:".now()->format('Y-m-d-H'),
            now()->addMinutes(3),
            fn (): array => $this->build($tenantId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function build(int $tenantId): array
    {
        $from = now()->startOfMonth();
        $to = now()->endOfMonth();
        $pl = app(AccountingReportService::class)->profitAndLoss($from, $to, $tenantId);

        return [
            'updated_at' => now()->toIso8601String(),
            'kpis' => $this->kpis($tenantId, $from, $to, $pl),
            'growth' => $this->monthlyGrowthChart($tenantId, 9),
            'clients' => $this->topDueClients($tenantId, 12),
        ];
    }

    /**
     * @param  array{income: float, expenses: float}  $pl
     * @return list<array{key: string, label: string, value: float, hint: string, tone: string, icon: string}>
     */
    private function kpis(int $tenantId, Carbon $from, Carbon $to, array $pl): array
    {
        $billing = app(StaffBillingKpiResolver::class)->resolve($tenantId);
        $fromIsp = ($billing['source'] ?? '') === 'isp_digital';
        $ispHint = $fromIsp ? ' (ISP Digital)' : '';

        $monthlyBill = (float) $billing['monthly_bill'];
        $collected = (float) $billing['collected_bill'];
        $totalDue = (float) $billing['due'];
        $discount = (float) $billing['discount'];

        if (! $fromIsp) {
            $discount = (float) Invoice::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
                ->whereNotIn('status', ['void', 'cancelled'])
                ->selectRaw('COALESCE(SUM(discount_amount + coupon_discount_amount), 0) as total')
                ->value('total');
        }

        $serviceSales = (float) InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.tenant_id', $tenantId)
            ->where('invoice_items.item_type', 'setup_fee')
            ->whereBetween('invoices.issue_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('invoices.status', ['void', 'cancelled'])
            ->sum('invoice_items.line_total');

        $productSales = (float) InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.tenant_id', $tenantId)
            ->whereIn('invoice_items.item_type', ['product', 'hardware', 'other'])
            ->whereBetween('invoices.issue_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('invoices.status', ['void', 'cancelled'])
            ->sum('invoice_items.line_total');

        return [
            [
                'key' => 'monthly_bill',
                'label' => 'Monthly Bill',
                'value' => round($monthlyBill, 2),
                'hint' => 'Current month total customer monthly bill'.$ispHint,
                'tone' => 'blue',
                'icon' => 'heroicon-o-calendar-days',
            ],
            [
                'key' => 'collected',
                'label' => 'Collected Bill',
                'value' => round($collected, 2),
                'hint' => 'Current month total received amount'.$ispHint,
                'tone' => 'teal',
                'icon' => 'heroicon-o-check-badge',
            ],
            [
                'key' => 'discount',
                'label' => 'Discount',
                'value' => round($discount, 2),
                'hint' => 'Current month total discount amount',
                'tone' => 'violet',
                'icon' => 'heroicon-o-receipt-percent',
            ],
            [
                'key' => 'total_due',
                'label' => 'Total Due',
                'value' => round(max(0, $totalDue), 2),
                'hint' => 'Total due bill of clients'.$ispHint,
                'tone' => 'slate',
                'icon' => 'heroicon-o-exclamation-circle',
            ],
            [
                'key' => 'service_sales',
                'label' => 'Service Sales Invoice',
                'value' => round($serviceSales, 2),
                'hint' => 'Monthly installation fee & other service sales',
                'tone' => 'sky',
                'icon' => 'heroicon-o-wrench-screwdriver',
            ],
            [
                'key' => 'product_sales',
                'label' => 'Product Sales Invoice',
                'value' => round($productSales, 2),
                'hint' => 'Current month total product sales',
                'tone' => 'cyan',
                'icon' => 'heroicon-o-shopping-bag',
            ],
            [
                'key' => 'income',
                'label' => 'Income',
                'value' => (float) ($pl['income'] ?? 0),
                'hint' => 'Current month total income amount',
                'tone' => 'indigo',
                'icon' => 'heroicon-o-arrow-trending-up',
            ],
            [
                'key' => 'expense',
                'label' => 'Expense',
                'value' => (float) ($pl['expenses'] ?? 0),
                'hint' => 'Current month total expense amount',
                'tone' => 'rose',
                'icon' => 'heroicon-o-arrow-trending-down',
            ],
        ];
    }

    /**
     * @return array{labels: list<string>, values: list<float>, max: float}
     */
    private function monthlyGrowthChart(int $tenantId, int $months): array
    {
        $labels = [];
        $values = [];
        $start = now()->subMonths($months - 1)->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $monthStart = $start->copy()->addMonths($i);
            $monthEnd = $monthStart->copy()->endOfMonth();
            $labels[] = $monthStart->format('M');

            $invoiced = (float) Invoice::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereBetween('issue_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->whereNotIn('status', ['void', 'cancelled'])
                ->sum('total');

            $values[] = round($invoiced, 0);
        }

        $max = max(1.0, ...$values);

        return [
            'labels' => $labels,
            'values' => $values,
            'max' => $max,
        ];
    }

    /**
     * @return list<array{login: string, phone: string, monthly_bill: float, due: float, url: string}>
     */
    private function topDueClients(int $tenantId, int $limit): array
    {
        $driver = Customer::query()->getConnection()->getDriverName();
        $totalDueSql = CustomerBalanceDue::resolvedBalanceDueExpression('customers', $driver);

        $rows = Customer::withoutGlobalScopes()
            ->where('customers.tenant_id', $tenantId)
            ->where('customers.status', CustomerStatus::ACTIVE)
            ->leftJoin('packages', 'packages.id', '=', 'customers.package_id')
            ->select([
                'customers.id',
                'customers.name',
                'customers.phone',
                'customers.mikrotik_secret_name',
                'customers.radius_username',
                'customers.customer_code',
                DB::raw('COALESCE(packages.price_monthly, 0) as monthly_bill'),
                DB::raw("{$totalDueSql} as total_due"),
            ])
            ->whereRaw("({$totalDueSql}) > 0.009")
            ->orderByDesc('total_due')
            ->limit($limit)
            ->get();

        return $rows->map(function (Customer $row): array {
            $login = $row->mikrotik_secret_name
                ?: $row->radius_username
                ?: $row->customer_code
                ?: $row->name;

            return [
                'login' => (string) $login,
                'phone' => (string) ($row->phone ?? '—'),
                'monthly_bill' => round((float) $row->monthly_bill, 2),
                'due' => round((float) $row->total_due, 2),
                'url' => \App\Filament\Resources\CustomerResource::getUrl('edit', ['record' => $row->id]),
            ];
        })->all();
    }

}
