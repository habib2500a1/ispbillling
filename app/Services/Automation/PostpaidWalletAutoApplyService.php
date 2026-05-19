<?php

namespace App\Services\Automation;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\InvoiceCalculator;
use App\Services\Payments\PaymentProcessor;
use App\Support\PaymentType;
use Illuminate\Support\Facades\DB;

final class PostpaidWalletAutoApplyService
{
    /**
     * @return array{customers: int, applied: float, invoices: int}
     */
    public function applyForTenant(?int $tenantId = null, bool $dryRun = false): array
    {
        $stats = ['customers' => 0, 'applied' => 0.0, 'invoices' => 0];

        $query = Customer::query()
            ->withoutGlobalScopes()
            ->where('billing_mode', 'postpaid')
            ->where('account_balance', '>', 0.01)
            ->whereIn('status', ['active', 'suspended']);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $query->orderBy('id')->chunkById(100, function ($customers) use (&$stats, $dryRun): void {
            foreach ($customers as $customer) {
                $result = $this->applyForCustomer($customer, $dryRun);
                if ($result['applied'] > 0) {
                    $stats['customers']++;
                    $stats['applied'] += $result['applied'];
                    $stats['invoices'] += $result['invoices'];
                }
            }
        });

        return $stats;
    }

    /**
     * @return array{applied: float, invoices: int}
     */
    public function applyForCustomer(Customer $customer, bool $dryRun = false): array
    {
        $wallet = round((float) $customer->account_balance, 2);
        if ($wallet <= 0) {
            return ['applied' => 0.0, 'invoices' => 0];
        }

        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['open', 'partial'])
            ->whereRaw('(total - amount_paid) > 0')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        if ($invoices->isEmpty()) {
            return ['applied' => 0.0, 'invoices' => 0];
        }

        $remaining = $wallet;
        $appliedTotal = 0.0;
        $invoiceCount = 0;

        foreach ($invoices as $invoice) {
            if ($remaining <= 0.009) {
                break;
            }

            $due = $invoice->balanceDue();
            if ($due <= 0) {
                continue;
            }

            $slice = round(min($remaining, $due), 2);
            if ($slice <= 0) {
                continue;
            }

            if ($dryRun) {
                $appliedTotal += $slice;
                $remaining -= $slice;
                $invoiceCount++;

                continue;
            }

            DB::transaction(function () use ($customer, $invoice, $slice, &$appliedTotal, &$remaining, &$invoiceCount): void {
                $payment = Payment::createTrusted([
                    'tenant_id' => $customer->tenant_id,
                    'customer_id' => $customer->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $slice,
                    'method' => 'wallet',
                    'reference' => 'auto-postpaid-wallet',
                    'status' => 'completed',
                    'paid_at' => now(),
                    'payment_type' => PaymentType::WALLET_APPLY,
                    'notes' => 'Automatic postpaid wallet apply',
                    'meta' => ['source' => 'isp:postpaid-pop-fund-credit'],
                ]);

                PaymentProcessor::processCompletedPayment($payment->fresh());

                $appliedTotal += $slice;
                $remaining -= $slice;
                $invoiceCount++;
            });
        }

        return ['applied' => round($appliedTotal, 2), 'invoices' => $invoiceCount];
    }
}
