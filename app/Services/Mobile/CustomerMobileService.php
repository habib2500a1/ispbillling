<?php

namespace App\Services\Mobile;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Network\CustomerConnectionStatusService;
use App\Services\Portal\CustomerPortalDashboardService;
use App\Services\Portal\PortalContentCatalog;
use App\Support\BandwidthDirection;
use App\Support\PortalPaymentGateways;
use App\Support\ResellerBranding;

class CustomerMobileService
{
    public function __construct(
        private readonly CustomerPortalDashboardService $portalDashboard,
        private readonly CustomerConnectionStatusService $connectionStatus,
    ) {}
    /**
     * @return array<string, mixed>
     */
    public function dashboard(Customer $customer): array
    {
        $customer->loadMissing('package');

        $totalDue = app(\App\Services\BillPayment\PublicBillPaymentService::class)->totalDue($customer);

        $recentBills = Invoice::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('issue_date')
            ->limit(5)
            ->get()
            ->map(fn (Invoice $inv): array => $this->invoiceSummary($inv));

        $portal = $this->portalDashboard->payload($customer);

        $monthlyBill = (float) ($customer->package?->price_monthly ?? 0);
        $paidThisMonth = (float) Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereBetween('issue_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('amount_paid');

        return [
            'customer' => $this->customerPayload($customer),
            'account_balance' => round((float) $customer->account_balance, 2),
            'total_due' => round($totalDue, 2),
            'summary' => [
                'monthly_bill' => round($monthlyBill, 2),
                'paid' => round($paidThisMonth, 2),
                'package_name' => $customer->package?->name ?? '—',
                'expire_date' => \App\Support\BillingDefaults::expireDayLabel($customer->service_expires_at?->toDateString()),
                'expire_day' => $customer->service_expires_at
                    ? \App\Support\BillingDefaults::expireDayFromDate($customer->service_expires_at->toDateString())
                    : null,
                'status' => ($portal['connection']['online'] ?? false) ? 'Connected' : 'Disconnected',
            ],
            'connection' => $portal['connection'] ?? null,
            'traffic' => $portal['traffic'] ?? null,
            'billing' => $portal['billing'] ?? null,
            'onu' => $portal['onu'] ?? null,
            'package' => $portal['package'] ?? null,
            'recent_bills' => $recentBills,
            'notices' => $this->noticesFor($customer),
            'gateways' => PortalPaymentGateways::forCustomerPortal($customer),
            'branding' => ResellerBranding::mobileBrandingPayload($customer),
            'require_full_payment' => ! config('bill_payment.allow_partial', false),
            'line_on_when_due_cleared' => true,
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function paymentHistory(Customer $customer, int $page = 1, int $perPage = 30): array
    {
        $payments = Payment::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->with('invoice:id,invoice_number,due_date')
            ->orderByDesc('paid_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $data = collect($payments->items())->map(fn (Payment $p): array => [
            'id' => $p->id,
            'amount' => round((float) $p->amount, 2),
            'method' => $p->methodLabel(),
            'paid_at' => $p->paid_at?->format('d-M-Y'),
            'paid_at_iso' => $p->paid_at?->toIso8601String(),
            'receipt_number' => $p->receipt_number,
            'invoice_id' => $p->invoice_id,
            'invoice_number' => $p->invoice?->invoice_number,
            'status' => 'Paid',
            'title' => $p->invoice_id ? 'Monthly Bill' : 'Payment',
        ])->values()->all();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'total' => $payments->total(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function invoiceDetail(Customer $customer, Invoice $invoice): array
    {
        $invoice->load(['items', 'payments' => fn ($q) => $q->where('status', 'completed')->orderByDesc('paid_at')]);

        $subtotal = round((float) $invoice->subtotal, 2);
        $total = round((float) $invoice->total, 2);
        $paid = round((float) $invoice->amount_paid, 2);
        $balance = round(max(0, $total - $paid), 2);

        $customer->loadMissing('package', 'mikrotikServer');

        return [
            'invoice' => array_merge($this->invoiceSummary($invoice), [
                'subtotal' => $subtotal,
                'previous_due' => 0,
                'balance_due' => $balance,
                'generation_date' => $invoice->issue_date?->format('d M Y'),
                'expire_date' => $invoice->due_date?->format('d M Y'),
                'period_label' => $invoice->issue_date?->format('M-y'),
                'note' => 'নিরবিচ্ছিন্ন সংযোগের জন্য সময় মতো বিল পরিশোধ করুন',
                'items' => $invoice->items->map(fn ($line) => [
                    'description' => $line->description,
                    'subtitle' => $customer->package?->download_mbps
                        ? $customer->package->download_mbps.'Mbps'
                        : null,
                    'quantity' => (float) $line->quantity,
                    'unit_price' => round((float) $line->unit_price, 2),
                    'line_total' => round((float) $line->line_total, 2),
                ])->values()->all(),
                'payments' => $invoice->payments->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'amount' => round((float) $p->amount, 2),
                    'method' => $p->methodLabel(),
                    'paid_at' => $p->paid_at?->format('d M Y'),
                ])->values()->all(),
            ]),
            'customer' => [
                'name' => $customer->name,
                'customer_code' => $customer->customer_code,
                'phone' => $customer->phone,
                'username' => $customer->pppLoginName(),
                'server' => $customer->mikrotikServer?->name ?? $customer->pppLoginName(),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function noticesFor(Customer $customer): array
    {
        try {
            return PortalContentCatalog::noticesForPortal((int) $customer->tenant_id)
                ->map(fn ($n) => [
                    'title' => $n->title,
                    'body' => $n->body,
                    'created_at' => $n->created_at?->toIso8601String(),
                ])
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function invoiceSummary(Invoice $invoice): array
    {
        $due = round((float) $invoice->total - (float) $invoice->amount_paid, 2);
        $invoice->loadMissing('customer');
        $customer = $invoice->customer;

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'total' => round((float) $invoice->total, 2),
            'amount_paid' => round((float) $invoice->amount_paid, 2),
            'balance_due' => $due,
            'can_pay' => $due > 0
                && ! in_array($invoice->status, ['void', 'cancelled', 'paid'], true)
                && ($customer !== null
                    ? (PortalPaymentGateways::forCustomerPortal($customer)['any'] ?? false)
                    : false),
            'pay_full_only' => ! config('bill_payment.allow_partial', false),
            'pdf_url' => route('portal.invoices.pdf', $invoice),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function customerPayload(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'customer_code' => $customer->customer_code,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'package' => $customer->package ? [
                'id' => $customer->package->id,
                'name' => $customer->package->name,
                'download_mbps' => $customer->package->download_mbps,
                'price_monthly' => (float) $customer->package->price_monthly,
            ] : null,
            'service_expires_at' => $customer->service_expires_at?->toIso8601String(),
            'is_online' => $customer->isPppOnline(),
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    public function usagePayload(array $stats, ?Customer $customer = null): array
    {
        $payload = [
            'online' => $stats['online'],
            'download_bps' => $stats['download_bps'],
            'upload_bps' => $stats['upload_bps'],
            'download_human' => BandwidthDirection::formatBps($stats['download_bps'] ?? null),
            'upload_human' => BandwidthDirection::formatBps($stats['upload_bps'] ?? null),
            'total_download' => $stats['total_download'],
            'total_upload' => $stats['total_upload'],
            'today_download' => $stats['today_download'],
            'today_upload' => $stats['today_upload'],
            'framed_ip' => $stats['framed_ip'],
            'session_started' => $stats['session_started'],
            'chart' => $stats['chart'],
            'chart_granularity' => is_array($stats['chart'] ?? null)
                ? ($stats['chart']['granularity'] ?? 'per_second')
                : 'per_second',
        ];

        if ($customer !== null) {
            $conn = $this->connectionStatus->summary($customer);
            $payload['connection_duration'] = $conn['connection_duration'];
            $payload['connection_duration_seconds'] = $conn['connection_duration_seconds'];
            $payload['session_started_formatted'] = $conn['session_started_formatted'];
            $payload['last_disconnect_at'] = $conn['last_disconnect_at'];
            $payload['last_disconnect_formatted'] = $conn['last_disconnect_formatted'];
            $payload['last_disconnect_human'] = $conn['last_disconnect_human'];
            $payload['portal_last_logout_at'] = $customer->portal_last_logout_at?->toIso8601String();
            $payload['portal_last_logout_formatted'] = $conn['portal_last_logout_at'];
        }

        return $payload;
    }
}
