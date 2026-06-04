<?php

namespace App\Services\Billing;

use App\Filament\Resources\InvoiceResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\PaymentCollectionSource;
use App\Support\PaymentType;
use Illuminate\Database\Eloquent\Builder;

/**
 * Invoice & collection lists aligned with legacy portal import (history + PDF print).
 */
final class SubscriberBillingStatementService
{
    public function invoiceHistoryLimit(): int
    {
        return max(12, (int) config('legacy_portal.bill_history_limit', 120));
    }

    public function paymentHistoryLimit(): int
    {
        return max(20, (int) config('legacy_portal.payment_history_limit', 120));
    }

    /**
     * @return Builder<Invoice>
     */
    public function invoiceHistoryQuery(Customer $customer): Builder
    {
        return Invoice::query()
            ->withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->orderByDesc('issue_date')
            ->orderByDesc('id');
    }

    /**
     * All monthly ISD bills + service invoices — including paid/void for print.
     *
     * @return list<array<string, mixed>>
     */
    public function invoiceHistoryRows(Customer $customer, ?int $limit = null): array
    {
        $limit ??= $this->invoiceHistoryLimit();

        return $this->invoiceHistoryQuery($customer)
            ->limit($limit)
            ->get()
            ->map(fn (Invoice $inv): array => $this->invoiceRow($inv))
            ->values()
            ->all();
    }

    /**
     * @return Builder<Payment>
     */
    public function paymentHistoryQuery(Customer $customer): Builder
    {
        return Payment::query()
            ->where('customer_id', $customer->id)
            ->with(['invoice:id,invoice_number', 'recorder:id,name'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function paymentHistoryRows(Customer $customer, string $source = 'all', ?int $limit = null): array
    {
        $limit ??= $this->paymentHistoryLimit();
        $payments = $this->paymentHistoryQuery($customer)->limit($limit * 2)->get();

        if ($source === 'legacy_portal') {
            $payments = $payments->filter(
                fn (Payment $p): bool => PaymentCollectionSource::isLegacyPortalImport($p)
                    && ($p->payment_type ?? PaymentType::PAYMENT) !== PaymentType::WALLET_APPLY,
            )->values();
        } elseif ($source === 'desk') {
            $payments = $payments->filter(
                fn (Payment $p): bool => ! PaymentCollectionSource::isLegacyPortalImport($p),
            )->values();
        }

        return $payments->take($limit)
            ->map(fn (Payment $payment): array => $this->paymentRow($payment))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function invoiceRow(Invoice $inv): array
    {
        $billMonth = null;
        $billMonth = \App\Support\LegacyPortalBillNotes::parseBillMonth((string) ($inv->notes ?? ''));

        return [
            'id' => $inv->id,
            'invoice_number' => $inv->invoice_number,
            'bill_month' => $billMonth,
            'issue_date' => $inv->issue_date?->toDateString(),
            'due_date' => $inv->due_date?->toDateString(),
            'period_label' => $inv->issue_date?->format('M Y') ?? '—',
            'total' => round((float) $inv->total, 2),
            'amount_paid' => round((float) $inv->amount_paid, 2),
            'balance_due' => $inv->balanceDue(),
            'status' => $inv->status,
            'is_overdue' => $inv->isOverdue(),
            'is_void' => $inv->status === 'void',
            'edit_url' => InvoiceResource::getUrl('edit', ['record' => $inv]),
            'pdf_url' => route('invoices.pdf', $inv),
            'print_url' => route('invoices.pdf', $inv),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function paymentRow(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'receipt_number' => $payment->receipt_number,
            'paid_at' => $payment->paid_at?->format('Y-m-d H:i') ?? '—',
            'amount' => round((float) $payment->amount, 2),
            'method' => $payment->methodLabel(),
            'status' => $payment->status,
            'payment_type' => $payment->typeLabel(),
            'invoice_id' => $payment->invoice_id,
            'invoice_number' => $payment->invoice?->invoice_number,
            'recorded_by' => $payment->recorder?->name ?? '—',
            'source_label' => PaymentCollectionSource::label($payment),
            'is_legacy_portal_import' => PaymentCollectionSource::isLegacyPortalImport($payment),
            'receipt_url' => route('payments.receipt', $payment),
        ];
    }
}
