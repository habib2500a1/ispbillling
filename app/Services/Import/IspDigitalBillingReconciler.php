<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Models\Invoice;

/**
 * Aligns local invoices with ISP Digital billing grid (single source of truth).
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
        if ($balanceDue <= 0.009) {
            $this->closeAllOpenInvoices($customer);

            return;
        }

        $this->closeSupersededMonthlyInvoices($customer, $currentInvoiceNumber);
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

    private function closeAllOpenInvoices(Customer $customer): void
    {
        Invoice::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['open', 'partial', 'sent', 'overdue'])
            ->each(function (Invoice $invoice): void {
                $invoice->updateTrusted([
                    'amount_paid' => $invoice->total,
                    'status' => 'paid',
                ]);
            });
    }

    private function closeSupersededMonthlyInvoices(Customer $customer, string $currentInvoiceNumber): void
    {
        $prefix = 'ISD-'.$customer->customer_code.'-';

        Invoice::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->where('invoice_number', 'like', $prefix.'%')
            ->where('invoice_number', '!=', $currentInvoiceNumber)
            ->whereIn('status', ['open', 'partial', 'sent', 'overdue'])
            ->each(function (Invoice $invoice): void {
                $invoice->updateTrusted([
                    'amount_paid' => $invoice->total,
                    'status' => 'paid',
                    'notes' => trim(($invoice->notes ?? '').' | Prior month closed — balance on current ISP Digital bill.'),
                ]);
            });
    }
}
