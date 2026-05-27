<?php

namespace App\Support;

final class CheckoutPaymentMeta
{
    /**
     * @param  array<string, mixed>  $session
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    public static function fromSession(array $session, array $base = []): array
    {
        $meta = $base;

        if (isset($session['return_to'])) {
            $meta['return_to'] = $session['return_to'];
        }

        if (isset($session['prepay_months'])) {
            $meta['prepay_months'] = max(1, (int) $session['prepay_months']);
        }

        $paymentType = (string) ($session['payment_type'] ?? PaymentType::PAYMENT);
        if ($paymentType === PaymentType::PREPAY) {
            $meta['fifo_multi_invoice'] = true;
        }

        return $meta;
    }
}
