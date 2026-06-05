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
     * Full money-receipt payload for mobile / API consumers.
     *
     * @return array<string, mixed>
     */
    public function receiptDetailPayload(Payment $payment): array
    {
        $payment->loadMissing(['customer', 'invoice', 'recorder:id,name']);

        $customer = $payment->customer;
        $invoice = $payment->invoice;
        $amounts = $this->receiptAmounts($payment, $invoice);

        return [
            'payment_id' => $payment->id,
            'receipt_number' => $payment->receipt_number,
            'paid_at' => $payment->paid_at?->format('d M Y, h:i A'),
            'paid_at_iso' => $payment->paid_at?->toIso8601String(),
            'method' => $payment->methodLabel(),
            'received_by' => $payment->recorder?->name ?? '—',
            'receipt_pdf_url' => url('/api/v1/staff/payments/'.$payment->id.'/receipt-pdf'),
            'branding' => ResellerBranding::mobileBrandingPayload($customer),
            'customer' => [
                'id' => $customer?->id,
                'name' => $customer?->name ?? '—',
                'customer_code' => $customer?->customer_code ?? '—',
                'username' => $customer?->pppLoginName() ?? '—',
                'phone' => $customer?->phone ?? '—',
            ],
            'amounts' => $amounts,
            'invoice' => $invoice instanceof Invoice ? [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
            ] : null,
            'footer_note' => $this->receiptFooterNote($customer),
        ];
    }

    /**
     * @return array<string, float>
     */
    public function receiptAmounts(Payment $payment, ?Invoice $invoice = null): array
    {
        $invoice ??= $payment->invoice;

        $totalBill = $invoice instanceof Invoice ? (float) $invoice->total : 0.0;
        $paidAmount = (float) $payment->amount;
        $discount = $invoice instanceof Invoice
            ? round((float) ($invoice->discount_amount ?? 0) + (float) ($invoice->coupon_discount_amount ?? 0), 2)
            : 0.0;
        $dueAmount = $invoice instanceof Invoice ? max(0, $invoice->balanceDue()) : 0.0;
        $vatAmount = $this->extractVatFromNotes($payment->notes);
        $advance = \App\Services\Billing\CollectionPaymentClassifier::isAdvancePayment($payment)
            ? $paidAmount
            : 0.0;

        return [
            'total_bill' => round($totalBill, 2),
            'paid_amount' => round($paidAmount, 2),
            'discount' => round($discount, 2),
            'due_amount' => round($dueAmount, 2),
            'vat_amount' => round($vatAmount, 2),
            'advance' => round($advance, 2),
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

    private function extractVatFromNotes(?string $notes): float
    {
        if ($notes === null || $notes === '') {
            return 0.0;
        }

        if (preg_match('/vat\s*:\s*([\d.]+)/i', $notes, $m)) {
            return (float) $m[1];
        }

        return 0.0;
    }

    private function receiptFooterNote(?\App\Models\Customer $customer): string
    {
        $vars = ResellerBranding::letterheadVars($customer);
        $footer = trim((string) ($vars['invoiceFooter'] ?? CompanyBranding::invoiceFooter()));

        if ($footer !== '') {
            return $footer;
        }

        return 'Thank you for your payment.';
    }
}
