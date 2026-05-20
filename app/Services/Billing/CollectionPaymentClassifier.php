<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\NotificationEvent;

/**
 * Classifies collection desk / mobile payments (bill vs advance / overpayment).
 */
final class CollectionPaymentClassifier
{
    /**
     * Advance = wallet top-up, overpayment, or advance-billing customer paying ahead — no collection note required.
     */
    public static function isAdvanceCollection(
        ?Customer $customer,
        ?Invoice $invoice,
        float $payAmount,
        float $discountBdt = 0.0,
    ): bool {
        if ($payAmount <= 0 && $discountBdt <= 0) {
            return false;
        }

        if ($invoice === null) {
            return $payAmount > 0;
        }

        $due = $invoice->balanceDue();
        if ($due <= 0) {
            return $payAmount > 0;
        }

        return ($payAmount + $discountBdt) > $due + 0.001;
    }

    public static function isAdvancePayment(Payment $payment): bool
    {
        if (($payment->payment_type ?? 'payment') !== 'payment') {
            return false;
        }

        if (($payment->meta['collection_type'] ?? '') === 'advance') {
            return true;
        }

        if ($payment->invoice_id === null && (float) $payment->amount > 0) {
            return true;
        }

        $walletCredit = (float) ($payment->meta['wallet_credit'] ?? 0);

        return $walletCredit > 0.009;
    }

    public static function noteRequired(
        ?Customer $customer,
        ?Invoice $invoice,
        float $payAmount,
        float $discountBdt,
    ): bool {
        if (self::isAdvanceCollection($customer, $invoice, $payAmount, $discountBdt)) {
            return false;
        }

        $settings = CollectionDiscountSettings::get();
        $balanceDue = $invoice?->balanceDue();

        if ($balanceDue === null || $balanceDue <= 0) {
            return $discountBdt > 0 && $settings['require_note_on_discount'];
        }

        $isPartial = ($payAmount + $discountBdt + 0.001) < $balanceDue;

        return ($isPartial && $settings['require_note_on_partial'])
            || ($discountBdt > 0 && $settings['require_note_on_discount']);
    }

    /**
     * @return array<string, mixed>
     */
    public static function paymentMeta(
        ?Customer $customer,
        ?Invoice $invoice,
        float $payAmount,
        float $discountBdt = 0.0,
        array $existing = [],
    ): array {
        if (! self::isAdvanceCollection($customer, $invoice, $payAmount, $discountBdt)) {
            return $existing;
        }

        return array_merge($existing, ['collection_type' => 'advance']);
    }

    public static function notificationEvent(Payment $payment): string
    {
        return self::isAdvancePayment($payment)
            ? NotificationEvent::PAYMENT_ADVANCE
            : NotificationEvent::PAYMENT_SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    public static function notificationVariables(Payment $payment): array
    {
        $customer = $payment->customer;
        $invoice = $payment->invoice;
        $walletCredit = (float) ($payment->meta['wallet_credit'] ?? 0);

        return [
            'name' => (string) ($customer?->name ?? 'Customer'),
            'amount' => number_format((float) $payment->amount, 2),
            'PaidAmount' => number_format((float) $payment->amount, 2),
            'invoice_number' => $invoice?->invoice_number ?? '—',
            'receipt_number' => $payment->receipt_number ?? '—',
            'payment_kind' => self::isAdvancePayment($payment) ? 'Advance (অগ্রিম)' : 'Bill payment',
            'wallet_credit' => $walletCredit > 0 ? number_format($walletCredit, 2) : '0.00',
        ];
    }
}
