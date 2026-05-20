<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Payment;

final class StaffPaymentApiPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function paymentPayload(Payment $payment): array
    {
        $payment->loadMissing(['invoice', 'customer:id,customer_code,name']);

        $invoice = $payment->invoice;

        return [
            'id' => $payment->id,
            'receipt_number' => $payment->receipt_number,
            'amount' => round((float) $payment->amount, 2),
            'method' => $payment->methodLabel(),
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'invoice_id' => $payment->invoice_id,
            'receipt_pdf_url' => url('/api/v1/staff/payments/'.$payment->id.'/receipt-pdf'),
            'invoice' => $invoice instanceof Invoice ? $this->invoicePayload($invoice) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function invoicePayload(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'total' => round((float) $invoice->total, 2),
            'amount_paid' => round((float) $invoice->amount_paid, 2),
            'balance_due' => $invoice->balanceDue(),
            'status' => $invoice->status,
            'pdf_url' => url('/api/v1/staff/invoices/'.$invoice->id.'/pdf'),
        ];
    }
}
