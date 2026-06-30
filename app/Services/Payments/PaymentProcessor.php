<?php

namespace App\Services\Payments;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\AdvanceInvoiceSyncService;
use App\Services\Billing\BillingDueRealtimeSync;
use App\Services\Billing\InvoiceCalculator;
use App\Support\PaymentGateway;
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
            return;
        }

        DB::transaction(function () use ($payment): void {
            $payment = $payment->fresh(['customer', 'invoice']);
            if ($payment === null) {
                return;
            }

            match ($payment->payment_type ?? PaymentType::PAYMENT) {
                PaymentType::REFUND => static::processRefund($payment),
                PaymentType::ADJUSTMENT => static::processAdjustment($payment),
                PaymentType::WALLET_DEPOSIT => static::processWalletDeposit($payment),
                PaymentType::WALLET_APPLY => static::processWalletApply($payment),
                default => static::processStandardPayment($payment),
            };

            static::markProcessed($payment);
        });

        $payment = $payment->fresh(['customer', 'invoice']);
        static::syncBillingWorkflow($payment);
        static::syncAdvancePrepay($payment->fresh(['customer', 'invoice']));
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
        string $paymentType = PaymentType::PAYMENT,
    ): Payment {
        return DB::transaction(function () use ($gateway, $transactionId, $customerId, $invoiceId, $amount, $reference, $meta, $paymentType): Payment {
            $existing = Payment::query()
                ->withoutGlobalScopes()
                ->where('gateway', $gateway)
                ->where('gateway_transaction_id', $transactionId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->status !== 'completed') {
                    $existing->update(['status' => 'completed', 'paid_at' => $existing->paid_at ?? now()]);
                }

                return $existing->fresh();
            }

            try {
                $payment = Payment::createTrusted([
                    'customer_id' => $customerId,
                    'invoice_id' => $invoiceId,
                    'amount' => round($amount, 2),
                    'method' => $gateway,
                    'gateway' => $gateway,
                    'gateway_transaction_id' => $transactionId,
                    'reference' => $reference,
                    'status' => 'completed',
                    'paid_at' => now(),
                    'payment_type' => $paymentType,
                    'receipt_number' => Payment::generateReceiptNumber(
                        (int) (Customer::withoutGlobalScopes()->whereKey($customerId)->value('tenant_id') ?? 1)
                    ),
                    'meta' => array_merge($meta, ['source' => 'gateway_webhook']),
                ]);
            } catch (QueryException $e) {
                if (! static::isDuplicateGatewayPaymentException($e)) {
                    throw $e;
                }

                $payment = Payment::query()
                    ->withoutGlobalScopes()
                    ->where('gateway', $gateway)
                    ->where('gateway_transaction_id', $transactionId)
                    ->firstOrFail();
            }

            return $payment->fresh();
        });
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
        $customer = static::lockedCustomer($payment->customer);
        if ($customer === null) {
            return;
        }

        $fifo = $payment->meta['fifo_allocations'] ?? null;
        if (is_array($fifo) && $fifo !== []) {
            $surplus = app(\App\Services\Resellers\ResellerPaymentAllocationService::class)
                ->applyFifoAllocations($payment, $customer, $fifo);

            if ($surplus > 0.009 && config('payments.overpayment_to_wallet', true)) {
                static::addWallet($customer, $surplus, $payment, 'fifo_surplus');
            }

            return;
        }

        if ($payment->invoice_id && $payment->invoice) {
            $invoice = $payment->invoice->fresh();
            $due = $invoice->balanceDue();
            $toInvoice = round(min($amount, $due), 2);

            if ($toInvoice > 0) {
                $invoice->forceFill([
                    'amount_paid' => round((float) $invoice->amount_paid + $toInvoice, 2),
                ])->save();
                InvoiceCalculator::recalculate($invoice->fresh());

                $meta = $payment->meta ?? [];
                $meta['invoice_applied'] = round(((float) ($meta['invoice_applied'] ?? 0)) + $toInvoice, 2);
                $payment->forceFill(['meta' => $meta])->saveQuietly();
            }

            $surplus = round($amount - $toInvoice, 2);
            if ($surplus > 0.009 && ! static::isAdvancePrepay($payment) && config('payments.overpayment_to_wallet', true)) {
                static::addWallet($customer, $surplus, $payment, 'overpayment');
            }

            return;
        }

        if (static::isAdvancePrepay($payment)) {
            return;
        }

        static::addWallet($customer, $amount, $payment, 'unallocated');
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
            $customer = static::lockedCustomer($payment->customer);
            if ($customer !== null) {
                static::addWallet($customer, $amount, $payment, 'refund_credit');
            }
        }
    }

    private static function processAdjustment(Payment $payment): void
    {
        $direction = (string) ($payment->meta['adjustment_direction'] ?? 'credit_invoice');
        $amount = (float) $payment->amount;

        if ($direction === 'credit_wallet' && $payment->customer) {
            $customer = static::lockedCustomer($payment->customer);
            if ($customer !== null) {
                static::addWallet($customer, $amount, $payment, 'adjustment');
            }

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
        $customer = static::lockedCustomer($payment->customer);
        if ($customer !== null) {
            static::addWallet($customer, (float) $payment->amount, $payment, 'deposit');
        }
    }

    private static function processWalletApply(Payment $payment): void
    {
        $customer = static::lockedCustomer($payment->customer);
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
                InvoiceCalculator::recalculate($invoice->fresh());

                $meta = $payment->meta ?? [];
                $meta['invoice_applied'] = round(((float) ($meta['invoice_applied'] ?? 0)) + $toInvoice, 2);
                $payment->forceFill(['meta' => $meta])->saveQuietly();
            }
        }
    }

    private static function addWallet(Customer $customer, float $amount, Payment $payment, string $reason): void
    {
        if ($amount <= 0) {
            return;
        }

        $locked = static::lockedCustomer($customer);
        if ($locked === null) {
            return;
        }

        $locked->forceFill([
            'account_balance' => round((float) $locked->account_balance + $amount, 2),
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

    private static function syncAdvancePrepay(?Payment $payment): bool
    {
        if ($payment === null || ! static::isAdvancePrepay($payment)) {
            return false;
        }

        $customer = $payment->customer?->fresh();
        if ($customer === null) {
            return false;
        }

        $meta = is_array($payment->meta) ? $payment->meta : [];
        $months = max(0, (int) ($meta['prepay_months'] ?? 0));
        if ($months < 1) {
            return false;
        }

        $forwardSync = app(AdvanceInvoiceSyncService::class);
        if (! $forwardSync->isEnabled()) {
            return false;
        }

        $touched = $forwardSync->syncForwardInvoices($customer, $months, $payment);

        $payment = $payment->fresh();
        $customer = $customer->fresh() ?? $customer;
        $remaining = static::unallocatedPaymentAmount($payment);
        if ($remaining > 0.009 && config('payments.overpayment_to_wallet', true)) {
            static::addWallet($customer, $remaining, $payment, 'prepay_surplus');
        }

        $payment = $payment->fresh() ?? $payment;
        $meta = is_array($payment->meta) ? $payment->meta : [];

        return $touched !== [] || ($meta['forward_invoice_synced'] ?? false) === true;
    }

    private static function syncBillingWorkflow(?Payment $payment): void
    {
        if ($payment === null || ! static::shouldRefreshBillingWorkflow($payment)) {
            return;
        }

        $customer = $payment->customer?->fresh();
        if ($customer === null) {
            return;
        }

        BillingDueRealtimeSync::afterPayment($customer, queueNetwork: true, payment: $payment);
    }

    private static function shouldRefreshBillingWorkflow(Payment $payment): bool
    {
        if (! $payment->customer_id) {
            return false;
        }

        $type = $payment->payment_type ?? PaymentType::PAYMENT;
        if (in_array($type, [PaymentType::WALLET_DEPOSIT, PaymentType::RESELLER_WALLET_RECHARGE, PaymentType::PLATFORM_SUBSCRIPTION], true)) {
            return false;
        }

        $meta = is_array($payment->meta) ? $payment->meta : [];

        return $payment->invoice_id !== null
            || static::isAdvancePrepay($payment)
            || is_array($meta['fifo_allocations'] ?? null);
    }

    private static function isAdvancePrepay(Payment $payment): bool
    {
        $meta = is_array($payment->meta) ? $payment->meta : [];

        return ($payment->payment_type ?? PaymentType::PAYMENT) === PaymentType::PREPAY
            || ((int) ($meta['prepay_months'] ?? 0)) > 0;
    }

    private static function unallocatedPaymentAmount(Payment $payment): float
    {
        $meta = is_array($payment->meta) ? $payment->meta : [];
        $applied = (float) ($meta['invoice_applied'] ?? 0);
        $walletCredit = (float) ($meta['wallet_credit'] ?? 0);

        return round((float) $payment->amount - $applied - $walletCredit, 2);
    }

    private static function lockedCustomer(?Customer $customer): ?Customer
    {
        if ($customer === null) {
            return null;
        }

        return Customer::withoutGlobalScopes()
            ->whereKey($customer->id)
            ->lockForUpdate()
            ->first();
    }

    private static function isDuplicateGatewayPaymentException(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'unique')
            && (str_contains($message, 'gateway_transaction') || str_contains($message, 'payments_gateway'));
    }
}
