<?php

namespace App\Support;

use App\Models\Payment;

final class PaymentAllocationBreakdown
{
    /**
     * @return array{
     *   payment_type: string,
     *   payment_type_label: string,
     *   to_invoice: float,
     *   to_wallet: float,
     *   from_wallet: float,
     *   unallocated: float,
     *   destination_label: string,
     *   destination_badge: string,
     * }
     */
    public static function for(Payment $payment): array
    {
        $amount = round((float) $payment->amount, 2);
        $meta = is_array($payment->meta) ? $payment->meta : [];
        $type = (string) ($payment->payment_type ?? PaymentType::PAYMENT);

        if ($type === PaymentType::WALLET_APPLY) {
            return [
                'payment_type' => $type,
                'payment_type_label' => PaymentType::label($type),
                'to_invoice' => $amount,
                'to_wallet' => 0.0,
                'from_wallet' => $amount,
                'unallocated' => 0.0,
                'destination_label' => 'Bill — paid from subscriber wallet',
                'destination_badge' => 'wallet_apply',
            ];
        }

        if ($type === PaymentType::WALLET_DEPOSIT) {
            return [
                'payment_type' => $type,
                'payment_type_label' => PaymentType::label($type),
                'to_invoice' => 0.0,
                'to_wallet' => $amount,
                'from_wallet' => 0.0,
                'unallocated' => 0.0,
                'destination_label' => 'Subscriber wallet (top-up)',
                'destination_badge' => 'wallet_deposit',
            ];
        }

        if ($type === PaymentType::REFUND) {
            return [
                'payment_type' => $type,
                'payment_type_label' => PaymentType::label($type),
                'to_invoice' => 0.0,
                'to_wallet' => $amount,
                'from_wallet' => 0.0,
                'unallocated' => 0.0,
                'destination_label' => 'Refund / reversed to customer',
                'destination_badge' => 'refund',
            ];
        }

        $toInvoice = round((float) ($meta['invoice_applied'] ?? 0), 2);
        $toWallet = round((float) ($meta['wallet_credit'] ?? 0), 2);

        if ($toInvoice <= 0 && $toWallet <= 0 && $amount > 0) {
            if ($payment->invoice_id) {
                $toInvoice = $amount;
            } else {
                $toWallet = $amount;
            }
        }

        $unallocated = round(max(0, $amount - $toInvoice - $toWallet), 2);

        $parts = [];
        if ($toInvoice > 0) {
            $parts[] = 'Bill '.number_format($toInvoice, 2).' BDT';
        }
        if ($toWallet > 0) {
            $parts[] = 'Wallet '.number_format($toWallet, 2).' BDT';
        }
        if ($unallocated > 0.009) {
            $parts[] = 'Unallocated '.number_format($unallocated, 2).' BDT';
        }

        return [
            'payment_type' => $type,
            'payment_type_label' => PaymentType::label($type),
            'to_invoice' => $toInvoice,
            'to_wallet' => $toWallet,
            'from_wallet' => 0.0,
            'unallocated' => $unallocated,
            'destination_label' => $parts !== [] ? implode(' · ', $parts) : '—',
            'destination_badge' => $toInvoice > 0 ? 'invoice' : ($toWallet > 0 ? 'wallet' : 'other'),
        ];
    }
}
