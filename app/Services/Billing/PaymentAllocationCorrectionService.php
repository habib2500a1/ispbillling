<?php

namespace App\Services\Billing;

use App\Models\Payment;
use App\Services\Payments\PaymentProcessor;
use App\Support\PaymentType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PaymentAllocationCorrectionService
{
    /**
     * Fix a completed collection applied to the wrong invoice or wrong amount.
     */
    public function reassign(
        Payment $payment,
        ?int $invoiceId,
        float $amount,
        ?string $reference = null,
        ?string $notes = null,
    ): Payment {
        if ($payment->status !== 'completed') {
            throw ValidationException::withMessages([
                'payment' => 'Only completed payments can be corrected here.',
            ]);
        }

        if (! in_array($payment->payment_type ?? PaymentType::PAYMENT, [PaymentType::PAYMENT, PaymentType::WALLET_APPLY], true)) {
            throw ValidationException::withMessages([
                'payment' => 'This payment type must be corrected from Payments module.',
            ]);
        }

        if ($amount < 0.01) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be at least 0.01 BDT.',
            ]);
        }

        if ($invoiceId !== null) {
            $ownsInvoice = $payment->customer_id
                && \App\Models\Invoice::query()
                    ->where('customer_id', $payment->customer_id)
                    ->whereKey($invoiceId)
                    ->exists();

            if (! $ownsInvoice) {
                throw ValidationException::withMessages([
                    'invoiceId' => 'Invoice does not belong to this subscriber.',
                ]);
            }
        }

        return DB::transaction(function () use ($payment, $invoiceId, $amount, $reference, $notes): Payment {
            $this->reverseProcessedPayment($payment);

            $meta = $payment->meta ?? [];
            unset($meta['processed'], $meta['processed_at'], $meta['invoice_credited'], $meta['invoice_applied'], $meta['wallet_credit'], $meta['wallet_reason']);
            $meta['corrected_at'] = now()->toIso8601String();
            $meta['corrected_by'] = auth()->id();
            $meta['correction_note'] = 'Reassigned from collection desk';

            $payment->forceFill([
                'invoice_id' => $invoiceId,
                'amount' => round($amount, 2),
                'reference' => $reference,
                'notes' => $notes,
                'recorded_by' => auth()->id(),
                'meta' => $meta,
            ])->save();

            PaymentProcessor::processCompletedPayment($payment->fresh(['customer', 'invoice']));

            return $payment->fresh(['invoice', 'recorder']);
        });
    }

    public function reverseProcessedPayment(Payment $payment): void
    {
        if (($payment->meta['processed'] ?? false) !== true) {
            return;
        }

        match ($payment->payment_type ?? PaymentType::PAYMENT) {
            PaymentType::REFUND => $this->reverseRefund($payment),
            PaymentType::WALLET_APPLY => $this->reverseWalletApply($payment),
            default => $this->reverseStandard($payment),
        };
    }

    private function reverseStandard(Payment $payment): void
    {
        $amount = (float) $payment->amount;

        if ($payment->invoice_id && $payment->invoice) {
            $invoice = $payment->invoice->fresh();
            $applied = (float) ($payment->meta['invoice_applied'] ?? min($amount, (float) $invoice->amount_paid));
            $toReverse = round(min($applied, (float) $invoice->amount_paid), 2);

            if ($toReverse > 0) {
                $invoice->forceFill([
                    'amount_paid' => max(0.0, round((float) $invoice->amount_paid - $toReverse, 2)),
                ])->save();
                InvoiceCalculator::recalculate($invoice->fresh());
            }
        }

        $wallet = (float) ($payment->meta['wallet_credit'] ?? 0);
        if ($wallet > 0.009 && $payment->customer) {
            $customer = $payment->customer->fresh();
            $customer?->forceFill([
                'account_balance' => max(0.0, round((float) $customer->account_balance - $wallet, 2)),
            ])->saveQuietly();
        }
    }

    private function reverseRefund(Payment $payment): void
    {
        $amount = (float) $payment->amount;

        if ($payment->invoice_id && $payment->invoice) {
            $invoice = $payment->invoice->fresh();
            $invoice->forceFill([
                'amount_paid' => round((float) $invoice->amount_paid + $amount, 2),
            ])->save();
            InvoiceCalculator::recalculate($invoice->fresh());
        } elseif ($payment->customer) {
            $customer = $payment->customer->fresh();
            $customer?->forceFill([
                'account_balance' => max(0.0, round((float) $customer->account_balance - $amount, 2)),
            ])->saveQuietly();
        }
    }

    private function reverseWalletApply(Payment $payment): void
    {
        $amount = (float) $payment->amount;

        if ($payment->customer) {
            $customer = $payment->customer->fresh();
            $customer?->forceFill([
                'account_balance' => round((float) $customer->account_balance + $amount, 2),
            ])->saveQuietly();
        }

        if ($payment->invoice_id && $payment->invoice) {
            $invoice = $payment->invoice->fresh();
            $applied = (float) ($payment->meta['invoice_applied'] ?? min($amount, (float) $invoice->amount_paid));
            $toReverse = round(min($applied, (float) $invoice->amount_paid), 2);
            if ($toReverse > 0) {
                $invoice->forceFill([
                    'amount_paid' => max(0.0, round((float) $invoice->amount_paid - $toReverse, 2)),
                ])->save();
                InvoiceCalculator::recalculate($invoice->fresh());
            }
        }
    }
}
