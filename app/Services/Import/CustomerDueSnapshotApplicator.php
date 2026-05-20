<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Models\Invoice;
use Carbon\Carbon;

/**
 * Applies ISP Digital–style due snapshot (payable, paid MTD, balance due) to a customer + current-month invoice.
 */
final class CustomerDueSnapshotApplicator
{
    public function __construct(
        private readonly int $tenantId = 1,
        private readonly ?IspDigitalBillingReconciler $reconciler = null,
    ) {}

    private function reconciler(): IspDigitalBillingReconciler
    {
        return $this->reconciler ?? app(IspDigitalBillingReconciler::class);
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

        $billTotal = round($balanceDue + $paid, 2);
        if ($billTotal <= 0.009 && $payable > 0) {
            $billTotal = $payable;
        }

        $periodKey = now()->format('Y-m');
        $number = 'ISD-'.$customer->customer_code.'-'.$periodKey;
        $issueDate = now()->startOfMonth();
        $dueDate ??= $issueDate->copy()->day(min(15, (int) $issueDate->daysInMonth));

        $notes = $sourceNote;
        if ($balanceDue > $payable + 0.009 && $payable > 0) {
            $notes .= ' · Includes '.number_format($balanceDue - $payable, 2).' BDT prior balance';
        }

        Invoice::withoutEvents(function () use ($customer, $number, $payable, $paid, $balanceDue, $billTotal, $issueDate, $dueDate, $notes): void {
            $attrs = [
                'tenant_id' => $this->tenantId,
                'customer_id' => $customer->id,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'period_start' => $issueDate->toDateString(),
                'period_end' => $issueDate->copy()->endOfMonth()->toDateString(),
                'subtotal' => $billTotal,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total' => $billTotal,
                'amount_paid' => $paid,
                'status' => $this->invoiceStatus($billTotal, $paid, $balanceDue),
                'notes' => $notes,
            ];

            $invoice = Invoice::query()->where('invoice_number', $number)->first();
            if ($invoice !== null) {
                $invoice->updateTrusted($attrs);
            } else {
                $attrs['invoice_number'] = $number;
                Invoice::createTrusted($attrs);
            }
        });

        $reconciler = $this->reconciler();
        $reconciler->reconcile($customer, $number, $payable, $paid, $balanceDue);

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $meta['isp_digital_balance_due'] = $balanceDue;
        $meta['isp_digital_payable'] = $payable;
        $meta['isp_digital_paid_mtd'] = $paid;
        $meta['isp_digital_advance'] = 0;
        $meta['isp_digital_payment_state'] = $reconciler->resolvePaymentState($balanceDue, $paid, $payable);
        $meta['isp_digital_billing_synced_at'] = now()->toIso8601String();
        $meta['due_snapshot_source'] = $sourceNote;
        $meta['due_snapshot_at'] = now()->toIso8601String();

        $customer->updateQuietly(['meta' => $meta]);
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
