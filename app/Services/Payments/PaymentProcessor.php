<?php

namespace App\Services\Payments;

use App\Jobs\SyncCustomerNetworkAccessJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\InvoiceCalculator;
use App\Services\Billing\OpenInvoiceResolver;
use App\Services\Billing\ServiceExpiryExtensionService;
use App\Services\Billing\BillingDueRealtimeSync;
use App\Services\Network\NetworkAccessCoordinator;
use App\Support\CustomerBalanceDue;
use App\Support\PaymentType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PaymentProcessor
{
    public static function processCompletedPayment(Payment $payment): void
    {
        if ($payment->status !== 'completed') {
            return;
        }

        if (($payment->meta['processed'] ?? false) === true) {
            static::syncCustomerDueMeta($payment);

            return;
        }

        $tenantId = 0;
        $customerId = 0;

        DB::transaction(function () use ($payment, &$tenantId, &$customerId): void {
            $payment = $payment->fresh(['customer', 'invoice']);
            if ($payment === null) {
                return;
            }

            match ($payment->payment_type ?? PaymentType::PAYMENT) {
                PaymentType::REFUND => static::processRefund($payment),
                PaymentType::ADJUSTMENT => static::processAdjustment($payment),
                PaymentType::WALLET_DEPOSIT => static::processWalletDeposit($payment),
                PaymentType::WALLET_APPLY => static::processWalletApply($payment),
                PaymentType::PREPAY => static::processPrepay($payment),
                default => static::processStandardPayment($payment),
            };

            static::markProcessed($payment);
            static::syncCustomerDueMeta($payment);

            $tenantId = (int) $payment->tenant_id;
            $customerId = $payment->customer_id ? (int) $payment->customer_id : 0;
        });

        if ($tenantId > 0 && $customerId > 0) {
            $customer = Customer::withoutGlobalScopes()->find($customerId);
            if ($customer !== null) {
                BillingDueRealtimeSync::flushCaches($tenantId);
                SyncCustomerNetworkAccessJob::dispatch($tenantId, $customerId)->afterResponse();
            }
        }
    }

    private static function syncCustomerDueMeta(Payment $payment): void
    {
        $customer = $payment->customer?->fresh();
        if ($customer === null) {
            return;
        }

        CustomerBalanceDue::refreshMetaAfterPayment($customer);
    }

    /**
     * Idempotent gateway payment (webhook / auto-detect).
     */
    public static function recordGatewayPayment(
        string $gateway,
        string $transactionId,
        int $customerId,
        ?int $invoiceId,
        float $amount,
        string $reference,
        array $meta = [],
        ?string $paymentType = null,
    ): Payment {
        $existing = Payment::query()
            ->withoutGlobalScopes()
            ->where('gateway', $gateway)
            ->where('gateway_transaction_id', $transactionId)
            ->first();

        if ($existing !== null) {
            if ($existing->status !== 'completed') {
                $existing->update(['status' => 'completed', 'paid_at' => $existing->paid_at ?? now()]);
            }

            return $existing->fresh();
        }

        $tenantId = (int) (Customer::withoutGlobalScopes()->whereKey($customerId)->value('tenant_id') ?? 1);

        try {
            $payment = Payment::createTrusted([
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'invoice_id' => $invoiceId,
                'amount' => round($amount, 2),
                'method' => $gateway,
                'gateway' => $gateway,
                'gateway_transaction_id' => $transactionId,
                'reference' => $reference,
                'status' => 'completed',
                'paid_at' => now(),
                'payment_type' => $paymentType ?? PaymentType::PAYMENT,
                'receipt_number' => Payment::generateReceiptNumber($tenantId),
                'meta' => array_merge($meta, ['source' => 'gateway_webhook']),
            ]);
        } catch (QueryException $e) {
            if (! self::isGatewayTrxUniqueViolation($e)) {
                throw $e;
            }

            $raceExisting = Payment::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('gateway', $gateway)
                ->where('gateway_transaction_id', $transactionId)
                ->first();

            if ($raceExisting === null) {
                throw $e;
            }

            return $raceExisting->fresh();
        }

        return $payment->fresh();
    }

    public static function recordRefund(Payment $original, float $amount, ?string $notes = null): Payment
    {
        $amount = round(max(0.01, $amount), 2);

        if ($amount > (float) $original->amount) {
            throw ValidationException::withMessages([
                'amount' => 'Refund cannot exceed the original payment amount.',
            ]);
        }

        $refund = Payment::createTrusted([
            'tenant_id' => $original->tenant_id,
            'customer_id' => $original->customer_id,
            'invoice_id' => $original->invoice_id,
            'parent_payment_id' => $original->id,
            'amount' => $amount,
            'method' => $original->method,
            'gateway' => $original->gateway,
            'reference' => 'REF-'.($original->reference ?? $original->id),
            'status' => 'completed',
            'paid_at' => now(),
            'payment_type' => PaymentType::REFUND,
            'receipt_number' => Payment::generateReceiptNumber((int) $original->tenant_id),
            'notes' => $notes,
            'recorded_by' => auth('web')->id(),
            'meta' => ['refund_of_payment_id' => $original->id],
        ]);

        return $refund;
    }

    private static function processStandardPayment(Payment $payment): void
    {
        $amount = (float) $payment->amount;
        $customer = $payment->customer;
        if ($customer === null) {
            return;
        }

        if (($payment->meta['fifo_multi_invoice'] ?? false) === true) {
            static::allocateFifoOpenInvoices($payment);

            return;
        }

        if (! $payment->invoice_id && $amount > 0.009) {
            $invoice = OpenInvoiceResolver::forCustomer($customer);
            if ($invoice !== null) {
                $payment->forceFill(['invoice_id' => $invoice->id])->saveQuietly();
                $payment->setRelation('invoice', $invoice);
            }
        }

        if ($payment->invoice_id && $payment->invoice) {
            $invoice = $payment->invoice->fresh();
            $due = $invoice->balanceDue();
            $toInvoice = round(min($amount, $due), 2);

            if ($toInvoice > 0) {
                $invoice->forceFill([
                    'amount_paid' => round((float) $invoice->amount_paid + $toInvoice, 2),
                ])->save();
                $invoice = $invoice->fresh();
                InvoiceCalculator::recalculate($invoice);

                $meta = $payment->meta ?? [];
                $meta['invoice_applied'] = round(((float) ($meta['invoice_applied'] ?? 0)) + $toInvoice, 2);
                $payment->forceFill(['meta' => $meta])->saveQuietly();
            }

            static::maybeActivateAfterInvoiceSettlement($customer, $invoice->fresh(), $payment);

            $surplus = round($amount - ($toInvoice ?? 0), 2);
            if ($surplus > 0.009 && config('payments.overpayment_to_wallet', true)) {
                static::addWallet($customer, $surplus, $payment, 'overpayment');
            }

            return;
        }

        static::addWallet($customer, $amount, $payment, 'unallocated');
    }

    /**
     * SMS / MFS auto-pay: apply amount across open bills (FIFO), surplus → wallet advance.
     */
    private static function allocateFifoOpenInvoices(Payment $payment): void
    {
        $customer = $payment->customer;
        if ($customer === null) {
            return;
        }

        $remaining = round((float) $payment->amount, 2);
        $allocations = [];
        $firstInvoiceId = null;

        foreach (OpenInvoiceResolver::openInvoicesWithBalance($customer) as $invoice) {
            if ($remaining <= 0.009) {
                break;
            }

            $due = round($invoice->balanceDue(), 2);
            $apply = round(min($remaining, $due), 2);
            if ($apply <= 0.009) {
                continue;
            }

            $invoice->forceFill([
                'amount_paid' => round((float) $invoice->amount_paid + $apply, 2),
            ])->save();
            $invoice = $invoice->fresh();
            InvoiceCalculator::recalculate($invoice);
            static::maybeActivateAfterInvoiceSettlement($customer, $invoice->fresh(), $payment);

            $firstInvoiceId ??= $invoice->id;
            $allocations[] = [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number ?? null,
                'amount' => $apply,
            ];
            $remaining = round($remaining - $apply, 2);
        }

        $meta = $payment->meta ?? [];
        $meta['invoice_allocations'] = $allocations;
        $meta['invoice_applied'] = round((float) collect($allocations)->sum('amount'), 2);

        if ($firstInvoiceId !== null) {
            $payment->forceFill([
                'invoice_id' => $firstInvoiceId,
                'meta' => $meta,
            ])->saveQuietly();
            $payment->setRelation('invoice', Invoice::withoutGlobalScopes()->find($firstInvoiceId));
        } else {
            $payment->forceFill(['meta' => $meta])->saveQuietly();
        }

        // For PREPAY (advance months), the "remaining" part is meant to extend service validity,
        // not to become a wallet credit. We handle any extra surplus later in processPrepay().
        if ($remaining > 0.009 && ($payment->payment_type ?? PaymentType::PAYMENT) !== PaymentType::PREPAY) {
            $reason = $allocations === [] ? 'advance' : 'overpayment';
            if (config('payments.overpayment_to_wallet', true)) {
                static::addWallet($customer, $remaining, $payment, $reason);
            }
        }
    }

    private static function processRefund(Payment $payment): void
    {
        $amount = (float) $payment->amount;

        if ($payment->invoice_id && $payment->invoice) {
            $invoice = $payment->invoice->fresh();
            $newPaid = max(0.0, round((float) $invoice->amount_paid - $amount, 2));
            $invoice->forceFill(['amount_paid' => $newPaid])->save();
            InvoiceCalculator::recalculate($invoice->fresh());
        } elseif ($payment->customer) {
            static::addWallet($payment->customer, $amount, $payment, 'refund_credit');
        }
    }

    private static function processAdjustment(Payment $payment): void
    {
        $direction = (string) ($payment->meta['adjustment_direction'] ?? 'credit_invoice');
        $amount = (float) $payment->amount;

        if ($direction === 'credit_wallet' && $payment->customer) {
            static::addWallet($payment->customer, $amount, $payment, 'adjustment');

            return;
        }

        if ($payment->invoice_id && $payment->invoice) {
            $invoice = $payment->invoice->fresh();
            if ($direction === 'debit_invoice') {
                $invoice->forceFill([
                    'amount_paid' => max(0.0, round((float) $invoice->amount_paid - $amount, 2)),
                ])->save();
            } else {
                $invoice->forceFill([
                    'amount_paid' => round((float) $invoice->amount_paid + $amount, 2),
                ])->save();
            }
            InvoiceCalculator::recalculate($invoice->fresh());
        }
    }

    private static function processWalletDeposit(Payment $payment): void
    {
        if ($payment->customer) {
            static::addWallet($payment->customer, (float) $payment->amount, $payment, 'deposit');
        }
    }

    private static function processPrepay(Payment $payment): void
    {
        $customer = $payment->customer;
        if ($customer === null) {
            return;
        }

        $months = max(1, (int) ($payment->meta['prepay_months'] ?? 1));

        $meta = $payment->meta ?? [];
        $meta['fifo_multi_invoice'] = true;
        $payment->forceFill(['meta' => $meta])->saveQuietly();

        static::allocateFifoOpenInvoices($payment->fresh(['customer']));

        app(ServiceExpiryExtensionService::class)->extendForPrepaidMonths(
            $customer->fresh() ?? $customer,
            $months,
            $payment,
        );

        $fresh = $customer->fresh() ?? $customer;
        if (CustomerBalanceDue::amount($fresh) <= 0.01) {
            app(ServiceExpiryExtensionService::class)->activateLineOnly($fresh);
        }

        $payment = $payment->fresh();
        $invoiceApplied = (float) ($payment->meta['invoice_applied'] ?? 0);
        $surplus = round((float) $payment->amount - $invoiceApplied, 2);
        $monthly = (float) (app(\App\Services\Billing\CustomerPrepayService::class)->monthlyRate($fresh) ?? 0);
        $prepayUsed = round($months * $monthly, 2);
        $walletAmount = round($surplus - $prepayUsed, 2);

        if ($walletAmount > 0.009 && config('payments.overpayment_to_wallet', true)) {
            static::addWallet($fresh, $walletAmount, $payment, 'prepay_surplus');
        }
    }

    private static function processWalletApply(Payment $payment): void
    {
        $customer = $payment->customer;
        if ($customer === null) {
            return;
        }

        $amount = (float) $payment->amount;
        if ((float) $customer->account_balance < $amount) {
            throw ValidationException::withMessages([
                'amount' => 'Insufficient wallet balance.',
            ]);
        }

        $customer->forceFill([
            'account_balance' => round((float) $customer->account_balance - $amount, 2),
        ])->saveQuietly();

        if ($payment->invoice_id && $payment->invoice) {
            $invoice = $payment->invoice->fresh();
            $due = $invoice->balanceDue();
            $toInvoice = round(min($amount, $due), 2);
            if ($toInvoice > 0) {
                $invoice->forceFill([
                    'amount_paid' => round((float) $invoice->amount_paid + $toInvoice, 2),
                ])->save();
                $invoice = $invoice->fresh();
                InvoiceCalculator::recalculate($invoice);
                static::maybeActivateAfterInvoiceSettlement($customer, $invoice->fresh(), $payment);

                $meta = $payment->meta ?? [];
                $meta['invoice_applied'] = round(((float) ($meta['invoice_applied'] ?? 0)) + $toInvoice, 2);
                $payment->forceFill(['meta' => $meta])->saveQuietly();
            }

            static::maybeActivateAfterInvoiceSettlement($customer, $invoice->fresh(), $payment);
        }
    }

    private static function maybeActivateAfterInvoiceSettlement(?Customer $customer, ?Invoice $invoice, ?Payment $payment = null): void
    {
        if ($customer === null || $invoice === null) {
            return;
        }

        if (($payment->payment_type ?? '') === PaymentType::PREPAY) {
            return;
        }

        if ($invoice->fresh()->balanceDue() > 0.01) {
            return;
        }

        app(ServiceExpiryExtensionService::class)->activateAfterFullPayment($customer->fresh() ?? $customer, $payment);
    }

    private static function addWallet(Customer $customer, float $amount, Payment $payment, string $reason): void
    {
        if ($amount <= 0) {
            return;
        }

        $customer->forceFill([
            'account_balance' => round((float) $customer->account_balance + $amount, 2),
        ])->saveQuietly();

        $meta = $payment->meta ?? [];
        $meta['wallet_credit'] = round($amount, 2);
        $meta['wallet_reason'] = $reason;
        $payment->forceFill(['meta' => $meta])->saveQuietly();
    }



    private static function markProcessed(Payment $payment): void
    {
        $meta = $payment->meta ?? [];
        $meta['processed'] = true;
        $meta['processed_at'] = now()->toIso8601String();
        $meta['invoice_credited'] = true;
        $payment->forceFill(['meta' => $meta])->saveQuietly();
    }

    private static function isGatewayTrxUniqueViolation(QueryException $e): bool
    {
        $code = (string) ($e->errorInfo[1] ?? '');

        return in_array($code, ['1062', '23505'], true);
    }

}
