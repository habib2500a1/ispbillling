<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\CustomerBalanceDue;
use Carbon\Carbon;

/**
 * Applies legacy portal billing grid to monthly + prior-balance invoices (visible in collection/history).
 */
final class CustomerDueSnapshotApplicator
{
    public function __construct(
        private readonly int $tenantId = 1,
        private readonly ?LegacyPortalBillingReconciler $reconciler = null,
    ) {}

    private function reconciler(): LegacyPortalBillingReconciler
    {
        return $this->reconciler ?? app(LegacyPortalBillingReconciler::class);
    }

    public function apply(
        Customer $customer,
        float $payable,
        float $paid,
        float $balanceDue,
        ?Carbon $dueDate = null,
        string $sourceNote = 'Due snapshot import',
    ): void {
        $payable = round(max(0, $payable), 2);
        $paid = round(max(0, $paid), 2);
        $balanceDue = round(max(0, $balanceDue), 2);

        $priorDue = round(max(0, $balanceDue - $payable), 2);
        $monthlyTotal = $payable > 0.009 ? $payable : round($balanceDue + $paid, 2);
        if ($monthlyTotal <= 0.009 && $balanceDue > 0) {
            $monthlyTotal = $balanceDue;
        }

        $paidToPrior = round(min($paid, $priorDue), 2);
        $paidToCurrent = round(max(0, $paid - $paidToPrior), 2);

        $periodKey = now()->format('Y-m');
        $currentNumber = 'ISD-'.$customer->customer_code.'-'.$periodKey;
        $issueDate = now()->startOfMonth();
        $dueDate ??= $issueDate->copy()->day(min(15, (int) $issueDate->daysInMonth));

        Invoice::withoutEvents(function () use (
            $customer,
            $currentNumber,
            $monthlyTotal,
            $paidToCurrent,
            $priorDue,
            $paidToPrior,
            $issueDate,
            $dueDate,
            $sourceNote,
        ): void {
            if ($priorDue > 0.009) {
                $priorNumber = 'ISD-'.$customer->customer_code.'-PRIOR-BALANCE';
                $this->upsertInvoice(
                    $customer,
                    $priorNumber,
                    $issueDate->copy()->subMonth()->startOfMonth(),
                    $dueDate,
                    $priorDue,
                    $paidToPrior,
                    $sourceNote.' · Prior balance (legacy portal)',
                );
            }

            $currentNotes = $sourceNote;
            if ($priorDue > 0.009) {
                $currentNotes .= ' · Current month ('.now()->format('M Y').')';
            }

            $this->upsertInvoice(
                $customer,
                $currentNumber,
                $issueDate,
                $dueDate,
                $monthlyTotal,
                $paidToCurrent,
                $currentNotes,
            );
        });

        $aligner = app(LegacyPortalInvoiceAligner::class);
        $aligner->voidStaleOpenInvoices($customer, $periodKey);
        $aligner->voidAllOpenInvoicesWhenIspDueZero($customer, $balanceDue);

        $reconciler = $this->reconciler();
        $reconciler->reconcile($customer, $currentNumber, $payable, $paid, $balanceDue);

        $customer->refresh();
        $resolved = CustomerBalanceDue::resolve($customer);

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $meta['balance_due'] = $balanceDue > 0.009 ? $balanceDue : $resolved['balance_due'];
        $meta['billing_payment_state'] = $reconciler->resolvePaymentState($balanceDue, $paid, $payable);
        $meta['legacy_portal_payable'] = $payable;
        $meta['legacy_portal_paid_mtd'] = $paid;
        $meta['legacy_portal_balance_due'] = $balanceDue;
        $meta['legacy_portal_advance'] = 0;
        $meta['legacy_portal_billing_synced_at'] = now()->toIso8601String();
        $meta['due_snapshot_source'] = $sourceNote;
        $meta['due_snapshot_at'] = now()->toIso8601String();
        $meta['local_due_synced_at'] = now()->toIso8601String();

        $customer->updateQuietly(['meta' => $meta]);
    }

    private function upsertInvoice(
        Customer $customer,
        string $number,
        Carbon $issueDate,
        Carbon $dueDate,
        float $total,
        float $paid,
        string $notes,
    ): void {
        $total = round(max(0, $total), 2);
        $paid = round(max(0, $paid), 2);
        $balanceDue = round(max(0, $total - $paid), 2);

        $attrs = [
            'tenant_id' => $this->tenantId,
            'customer_id' => $customer->id,
            'issue_date' => $issueDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'period_start' => $issueDate->toDateString(),
            'period_end' => $issueDate->copy()->endOfMonth()->toDateString(),
            'subtotal' => $total,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => $total,
            'amount_paid' => $paid,
            'status' => $this->invoiceStatus($total, $paid, $balanceDue),
            'notes' => $notes,
        ];

        $invoice = Invoice::query()->where('invoice_number', $number)->first();
        if ($invoice !== null) {
            $attrs = $this->mergeLocalCollectionIntoInvoiceAttrs($invoice, $attrs);
            $invoice->updateTrusted($attrs);
        } else {
            $attrs['invoice_number'] = $number;
            Invoice::createTrusted($attrs);
        }
    }

    /**
     * legacy portal grid can lag behind local collection — never downgrade a paid local invoice.
     *
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    private function mergeLocalCollectionIntoInvoiceAttrs(Invoice $invoice, array $attrs): array
    {
        $total = round((float) ($attrs['total'] ?? $invoice->total), 2);
        $ispPaid = round((float) ($attrs['amount_paid'] ?? 0), 2);
        $localPaid = round((float) $invoice->amount_paid, 2);

        $paymentsSum = round((float) Payment::withoutGlobalScopes()
            ->where('invoice_id', $invoice->id)
            ->where('status', 'completed')
            ->whereIn('payment_type', ['payment', 'wallet_apply'])
            ->sum('amount'), 2);

        $effectivePaid = max($ispPaid, $localPaid, $paymentsSum);
        $balanceDue = round(max(0, $total - $effectivePaid), 2);

        $attrs['amount_paid'] = $effectivePaid;
        $attrs['status'] = $this->invoiceStatus($total, $effectivePaid, $balanceDue);

        return $attrs;
    }

    private function invoiceStatus(float $billTotal, float $paid, float $balanceDue): string
    {
        if ($billTotal <= 0 || $balanceDue <= 0.009) {
            return 'paid';
        }
        if ($paid > 0.009) {
            return 'partial';
        }

        return 'open';
    }
}
