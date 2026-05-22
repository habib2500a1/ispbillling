<?php

namespace App\Services\Mobile;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

final class StaffBillingMobileService
{
    public function __construct(
        private readonly StaffBillingKpiResolver $billingKpis,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(User $user): array
    {
        $tenantId = \App\Support\StaffTenantScope::tenantIdFor($user);
        $billing = $this->billingKpis->resolve($tenantId);
        $cached = app(\App\Services\Import\IspDigitalCurrentBillingSyncService::class)->cachedSummary($tenantId);

        return [
            'billing' => array_merge($billing, [
                'paid_clients' => (int) ($cached['total_paid_clients'] ?? 0),
                'unpaid_clients' => (int) ($cached['total_unpaid_clients'] ?? 0),
                'synced_at' => $cached['synced_at'] ?? null,
            ]),
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function dueList(int $tenantId, int $page = 1, int $perPage = 30): array
    {
        $driver = Customer::query()->getConnection()->getDriverName();
        $dueExpr = \App\Support\CustomerBalanceDue::resolvedBalanceDueExpression('customers', $driver);

        $customers = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['package:id,name,download_mbps,price_monthly', 'zone:id,name', 'subzone:id,name', 'area:id,name'])
            ->whereRaw("{$dueExpr} > 0.009")
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);

        $data = collect($customers->items())
            ->map(function (Customer $c): array {
                $row = MobileCustomerListSerializer::row($c);
                $meta = is_array($c->meta) ? $c->meta : [];
                $row['monthly_payable'] = round((float) ($meta['isp_digital_payable'] ?? 0), 2);

                return $row;
            })
            ->values()
            ->all();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'total' => $customers->total(),
            ],
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function invoices(int $tenantId, Request $request): array
    {
        $status = $request->query('status');
        $month = $request->query('month');

        $query = Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['customer:id,customer_code,name,phone'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id');

        if (is_string($status) && $status !== '' && $status !== 'all') {
            if ($status === 'due') {
                $query->whereIn('status', ['open', 'partial'])
                    ->whereRaw('(total - COALESCE(amount_paid, 0)) > 0.009');
            } else {
                $query->where('status', $status);
            }
        }

        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $from = Carbon::parse($month.'-01')->startOfMonth();
            $query->whereBetween('issue_date', [$from->toDateString(), $from->copy()->endOfMonth()->toDateString()]);
        }

        $page = max(1, (int) $request->query('page', 1));
        $invoices = $query->paginate(30, ['*'], 'page', $page);

        $data = collect($invoices->items())->map(fn (Invoice $inv) => [
            'id' => $inv->id,
            'invoice_number' => $inv->invoice_number,
            'customer_id' => $inv->customer_id,
            'customer_name' => $inv->customer?->name,
            'customer_code' => $inv->customer?->customer_code,
            'issue_date' => $inv->issue_date?->toDateString(),
            'due_date' => $inv->due_date?->toDateString(),
            'total' => round((float) $inv->total, 2),
            'amount_paid' => round((float) $inv->amount_paid, 2),
            'balance_due' => $inv->balanceDue(),
            'discount' => round((float) ($inv->discount_amount ?? 0) + (float) ($inv->coupon_discount_amount ?? 0), 2),
            'status' => $inv->status,
            'is_overdue' => $inv->isOverdue(),
        ])->values()->all();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'total' => $invoices->total(),
            ],
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function collections(int $tenantId, Request $request): array
    {
        $from = $request->query('from')
            ? Carbon::parse((string) $request->query('from'))->startOfDay()
            : now()->startOfMonth();
        $to = $request->query('to')
            ? Carbon::parse((string) $request->query('to'))->endOfDay()
            : now()->endOfDay();

        $page = max(1, (int) $request->query('page', 1));

        $payments = Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->with(['customer:id,customer_code,name,phone', 'invoice:id,invoice_number,due_date', 'recorder:id,name'])
            ->orderByDesc('paid_at')
            ->paginate(40, ['*'], 'page', $page);

        $data = collect($payments->items())->map(fn (Payment $p) => [
            'id' => $p->id,
            'receipt_number' => $p->receipt_number,
            'amount' => round((float) $p->amount, 2),
            'method' => $p->methodLabel(),
            'paid_at' => $p->paid_at?->format('Y-m-d H:i'),
            'customer_id' => $p->customer_id,
            'customer_name' => $p->customer?->name,
            'customer_code' => $p->customer?->customer_code,
            'invoice_number' => $p->invoice?->invoice_number,
            'bill_date' => $p->invoice?->due_date?->toDateString(),
            'recorded_by' => $p->recorder?->name ?? '—',
            'recorded_by_id' => $p->recorded_by,
            'reference' => $p->reference,
            'notes' => $p->notes,
        ])->values()->all();

        $monthCollected = (float) Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        $monthDiscount = (float) Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('issue_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(discount_amount, 0) + COALESCE(coupon_discount_amount, 0)'));

        $periodCollected = (float) Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount');

        return [
            'summary' => [
                'transaction_count' => count($data),
                'period_collected' => round($periodCollected, 2),
                'month_collected' => round($monthCollected, 2),
                'month_discount' => round(abs($monthDiscount), 2),
            ],
            'data' => $data,
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'total' => $payments->total(),
            ],
        ];
    }

}
