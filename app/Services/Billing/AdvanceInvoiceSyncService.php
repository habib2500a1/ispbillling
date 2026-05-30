<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * When prepaid/advance subscribers pay, create forward-period invoices in the same flow
 * so bills and service extension stay aligned (শেই সাথে বিল + লাইন).
 */
final class AdvanceInvoiceSyncService
{
    public function isEnabled(): bool
    {
        return (bool) config('billing.prepaid_forward_invoices_on_payment', true);
    }

    /**
     * @return list<int> Invoice IDs created or marked during this sync
     */
    public function syncForwardInvoices(Customer $customer, int $months, ?Payment $payment = null): array
    {
        if (! $this->isEnabled() || $months < 1) {
            return [];
        }

        $customer = $customer->fresh() ?? $customer;
        if (! $this->isPrepaidLike($customer) || ! $customer->shouldGenerateInvoice()) {
            return [];
        }

        $customer->loadMissing('package');
        $package = $customer->package;
        if (! $package instanceof Package) {
            return [];
        }

        $months = max(1, min(36, $months));
        $touched = [];
        $latest = $this->latestInvoice($customer);

        for ($i = 0; $i < $months; $i++) {
            $reference = $this->nextBillingReference($customer, $package, $latest);
            $invoice = InvoiceGenerator::generateForCustomer($customer, $reference, true, null);

            if ($invoice === null) {
                $invoice = $this->findInvoiceForReference($customer, $package, $reference);
            }

            if ($invoice === null) {
                break;
            }

            $latest = $invoice->fresh();
            $this->settleForwardInvoice($customer, $invoice, $payment);
            $touched[] = (int) $invoice->id;
        }

        return $touched;
    }

    private function isPrepaidLike(Customer $customer): bool
    {
        return in_array($customer->billing_mode ?? '', ['prepaid', 'advance'], true);
    }

    private function latestInvoice(Customer $customer): ?Invoice
    {
        return Invoice::query()
            ->withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['void', 'cancelled'])
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->first();
    }

    private function nextBillingReference(
        Customer $customer,
        Package $package,
        ?Invoice $latest,
    ): CarbonInterface {
        if ($latest !== null && $latest->issue_date !== null) {
            $reference = Carbon::parse($latest->issue_date)->addMonth()->startOfDay();
        } elseif ($latest !== null && $latest->period_end !== null) {
            $reference = Carbon::parse($latest->period_end)->addDay()->startOfDay();
        } else {
            $reference = now()->startOfDay();
        }

        $billingDay = max(1, min(28, (int) ($customer->billing_day ?: 1)));
        $reference = $reference->copy()->day(min($billingDay, $reference->daysInMonth));

        if (! InvoiceGenerator::shouldBillOnDate($customer, $package, $reference, false)) {
            $reference = $reference->copy()->addMonth()->day(
                min($billingDay, $reference->copy()->addMonth()->daysInMonth),
            );
        }

        return $reference;
    }

    private function findInvoiceForReference(
        Customer $customer,
        Package $package,
        CarbonInterface $reference,
    ): ?Invoice {
        [$periodStart, $periodEnd] = BillingPeriodResolver::resolve($package, $reference);

        return Invoice::query()
            ->withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->whereDate('period_start', $periodStart->toDateString())
            ->whereDate('period_end', $periodEnd->toDateString())
            ->whereNotIn('status', ['void', 'cancelled'])
            ->first();
    }

    private function settleForwardInvoice(Customer $customer, Invoice $invoice, ?Payment $payment): void
    {
        $invoice = $invoice->fresh();
        if ($invoice === null || $invoice->balanceDue() <= 0.01) {
            return;
        }

        if ($payment !== null) {
            $remaining = $this->paymentRemaining($payment);
            if ($remaining > 0.009) {
                $apply = round(min($remaining, $invoice->balanceDue()), 2);
                $invoice->forceFill([
                    'amount_paid' => round((float) $invoice->amount_paid + $apply, 2),
                ])->save();
                $invoice = $invoice->fresh();
                InvoiceCalculator::recalculate($invoice);
                $this->recordPaymentAllocation($payment, $invoice, $apply);

                return;
            }
        }

        $this->markPrepaidCovered($invoice, $payment);
    }

    private function markPrepaidCovered(Invoice $invoice, ?Payment $payment): void
    {
        $invoice = $invoice->fresh();
        if ($invoice === null || $invoice->balanceDue() <= 0.01) {
            return;
        }

        $due = round($invoice->balanceDue(), 2);
        $invoice->forceFill([
            'amount_paid' => round((float) $invoice->amount_paid + $due, 2),
        ])->save();
        $invoice = $invoice->fresh();
        InvoiceCalculator::recalculate($invoice);

        if ($payment !== null) {
            $this->recordPaymentAllocation($payment, $invoice, $due, true);
        }
    }

    private function paymentRemaining(Payment $payment): float
    {
        $meta = is_array($payment->meta) ? $payment->meta : [];
        $applied = (float) ($meta['invoice_applied'] ?? 0);
        $forwardCovered = (float) ($meta['forward_invoice_covered'] ?? 0);

        return round((float) $payment->amount - $applied - $forwardCovered, 2);
    }

    private function recordPaymentAllocation(
        Payment $payment,
        Invoice $invoice,
        float $amount,
        bool $prepaidCovered = false,
    ): void {
        $meta = is_array($payment->meta) ? $payment->meta : [];
        $allocations = is_array($meta['invoice_allocations'] ?? null) ? $meta['invoice_allocations'] : [];
        $allocations[] = [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number ?? null,
            'amount' => $amount,
            'prepaid_covered' => $prepaidCovered,
        ];
        $meta['invoice_allocations'] = $allocations;
        $meta['invoice_applied'] = round((float) ($meta['invoice_applied'] ?? 0) + $amount, 2);
        if ($prepaidCovered) {
            $meta['forward_invoice_covered'] = round((float) ($meta['forward_invoice_covered'] ?? 0) + $amount, 2);
            $forwardIds = is_array($meta['forward_invoice_ids'] ?? null) ? $meta['forward_invoice_ids'] : [];
            $forwardIds[] = $invoice->id;
            $meta['forward_invoice_ids'] = array_values(array_unique($forwardIds));
        }

        $payment->forceFill(['meta' => $meta])->saveQuietly();
    }
}
