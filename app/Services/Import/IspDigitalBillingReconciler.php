<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\CustomerBalanceDue;
use App\Support\PaymentType;

/**
 * Aligns local invoices with ISP Digital billing — keeps each month's bill visible until actually paid.
 */
final class IspDigitalBillingReconciler
{
    public function reconcile(
        Customer $customer,
        string $currentInvoiceNumber,
        float $payable,
        float $paidMtd,
        float $balanceDue,
    ): void {
        if ($balanceDue > 0.009) {
            return;
        }

        if (CustomerBalanceDue::invoiceBalanceDue($customer) > 0.009) {
            return;
        }

        $this->closeAllOpenInvoices($customer);
    }

    public function resolvePaymentState(float $balanceDue, float $paidMtd, float $payable): string
    {
        if ($balanceDue <= 0.009) {
            return 'paid';
        }
        if ($paidMtd > 0.009) {
            return 'partial';
        }

        return 'unpaid';
    }

    /**
     * @param  array<string, mixed>  $billingRow
     */
    public function resolveBillingMode(Customer $customer, array $billingRow, float $balanceDue, float $paidMtd, float $payable, float $advance): string
    {
        if ($advance > 0.009) {
            return 'advance';
        }

        $expires = $customer->service_expires_at;
        if ($expires !== null && $expires->isFuture() && $expires->year >= 2000 && $expires->year < 2038) {
            return 'prepaid';
        }

        return 'postpaid';
    }

    /**
     * Undo ISP "consolidated" closes so old monthly due bills show again in collection/history.
     */
    public function reopenConsolidatedMonthlyInvoices(?int $tenantId = null): int
    {
        $reopened = 0;

        $query = Invoice::withoutGlobalScopes()
            ->where('notes', 'like', '%Prior month closed%');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $query->each(function (Invoice $invoice) use (&$reopened): void {
            $paymentsSum = round((float) Payment::withoutGlobalScopes()
                ->where('invoice_id', $invoice->id)
                ->where('status', 'completed')
                ->whereIn('payment_type', [PaymentType::PAYMENT, PaymentType::WALLET_APPLY])
                ->sum('amount'), 2);

            $total = round((float) $invoice->total, 2);
            $balanceDue = round(max(0, $total - $paymentsSum), 2);
            $notes = (string) ($invoice->notes ?? '');
            $notes = trim(preg_replace('/\s*\|\s*Prior month closed — balance on current ISP Digital bill\./', '', $notes) ?? $notes);

            $invoice->updateTrusted([
                'amount_paid' => $paymentsSum,
                'status' => $this->statusFromAmounts($total, $paymentsSum, $balanceDue),
                'notes' => $notes !== '' ? $notes : null,
            ]);

            $reopened++;
        });

        return $reopened;
    }

    private function closeAllOpenInvoices(Customer $customer): void
    {
        Invoice::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['open', 'partial', 'sent', 'overdue'])
            ->each(function (Invoice $invoice): void {
                $paymentsSum = round((float) Payment::withoutGlobalScopes()
                    ->where('invoice_id', $invoice->id)
                    ->where('status', 'completed')
                    ->whereIn('payment_type', [PaymentType::PAYMENT, PaymentType::WALLET_APPLY])
                    ->sum('amount'), 2);

                $total = round((float) $invoice->total, 2);
                if ($total <= 0.009 || $paymentsSum < $total - 0.009) {
                    return;
                }

                $invoice->updateTrusted([
                    'amount_paid' => $paymentsSum,
                    'status' => 'paid',
                ]);
            });
    }

    private function statusFromAmounts(float $total, float $paid, float $balanceDue): string
    {
        if ($total <= 0 || $balanceDue <= 0.009) {
            return 'paid';
        }
        if ($paid > 0.009) {
            return 'partial';
        }

        return 'open';
    }
}
