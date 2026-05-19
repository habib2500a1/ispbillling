<?php

namespace App\Services\Accounting;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Collector\CollectorSettlementService;

class AccountingIntegrationService
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    public function postCustomerPayment(Payment $payment): void
    {
        if (! config('accounting.auto_post_customer_payments', true)) {
            return;
        }

        if ($payment->status !== 'completed' || $payment->amount <= 0) {
            return;
        }

        $existing = \App\Models\JournalEntry::withoutGlobalScopes()
            ->where('source_type', 'customer_payment')
            ->where('source_id', $payment->id)
            ->exists();

        if ($existing) {
            return;
        }

        $amount = (float) $payment->amount;
        $cashMethods = ['cash', 'counter'];
        $isCash = in_array($payment->method, $cashMethods, true);

        $collectorService = app(CollectorSettlementService::class);
        if ($collectorService->qualifiesForCollectorTracking($payment)) {
            $debitCode = (string) config('collector.holding_account_code', '1050');
        } else {
            $debitCode = $isCash
                ? config('accounting.cash_account_code', '1000')
                : config('accounting.bank_account_code', '1100');
        }

        $creditCode = config('accounting.auto_post_invoices', false)
            ? config('accounting.ar_account_code', '1200')
            : config('accounting.revenue_account_code', '4000');

        $this->ledger->post(
            'Customer payment '.$payment->receipt_number,
            [
                ['account_code' => $debitCode, 'debit' => $amount],
                ['account_code' => $creditCode, 'credit' => $amount],
            ],
            $payment->paid_at ?? $payment->created_at,
            'customer_payment',
            $payment->id,
            (int) $payment->tenant_id,
        );
    }

    public function postIssuedInvoice(Invoice $invoice): void
    {
        if (! config('accounting.auto_post_invoices', false)) {
            return;
        }

        if (! in_array($invoice->status, ['issued', 'partial', 'overdue'], true)) {
            return;
        }

        $total = (float) $invoice->total;
        if ($total <= 0) {
            return;
        }

        $existing = \App\Models\JournalEntry::withoutGlobalScopes()
            ->where('source_type', 'invoice')
            ->where('source_id', $invoice->id)
            ->exists();

        if ($existing) {
            return;
        }

        $subtotal = (float) $invoice->subtotal;
        $tax = (float) ($invoice->tax_amount ?? 0);
        $revenue = max(0, round($subtotal, 2));

        $lines = [
            ['account_code' => config('accounting.ar_account_code', '1200'), 'debit' => $total],
        ];

        if ($revenue > 0) {
            $lines[] = ['account_code' => config('accounting.revenue_account_code', '4000'), 'credit' => $revenue];
        }

        if ($tax > 0) {
            $lines[] = ['account_code' => config('accounting.vat_payable_code', '2100'), 'credit' => $tax];
        }

        $creditSum = collect($lines)->sum(fn (array $l): float => (float) ($l['credit'] ?? 0));
        $remainder = round($total - $creditSum, 2);
        if (abs($remainder) > 0.01) {
            $lines[] = ['account_code' => config('accounting.revenue_account_code', '4000'), 'credit' => $remainder];
        }

        $this->ledger->post(
            'Invoice '.$invoice->invoice_number,
            $lines,
            $invoice->issued_at ?? $invoice->created_at,
            'invoice',
            $invoice->id,
            (int) $invoice->tenant_id,
        );
    }
}
