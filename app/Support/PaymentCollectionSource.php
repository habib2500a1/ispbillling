<?php

namespace App\Support;

use App\Models\Payment;

/**
 * Where a collection row came from (legacy portal import vs local desk / wallet).
 */
final class PaymentCollectionSource
{
    public static function label(Payment $payment): string
    {
        if (self::isLegacyPortalImport($payment)) {
            return BillingPortalLabel::paymentSource();
        }

        if (($payment->payment_type ?? PaymentType::PAYMENT) === PaymentType::WALLET_APPLY) {
            return 'Wallet (desk)';
        }

        if (($payment->payment_type ?? PaymentType::PAYMENT) === PaymentType::REFUND) {
            return 'Refund';
        }

        return 'Collection desk';
    }

    public static function isLegacyPortalImport(Payment $payment): bool
    {
        $meta = is_array($payment->meta) ? $payment->meta : [];

        return LegacyPortalSource::isImportedSource($meta['import_source'] ?? null);
    }

    /**
     * Rows that should match pay.anetbd.com payment history (excludes desk wallet-apply bookkeeping).
     *
     * @param  iterable<Payment>  $payments
     * @return list<Payment>
     */
    public static function filterLegacyPortalParity(iterable $payments): array
    {
        $out = [];
        foreach ($payments as $payment) {
            if (! $payment instanceof Payment) {
                continue;
            }
            if (($payment->payment_type ?? PaymentType::PAYMENT) === PaymentType::WALLET_APPLY) {
                continue;
            }
            if (self::isLegacyPortalImport($payment)) {
                $out[] = $payment;

                continue;
            }
        }

        return $out;
    }

    /**
     * @param  iterable<Payment>  $payments
     * @return list<Payment>
     */
    public static function filterLocalOnly(iterable $payments): array
    {
        $out = [];
        foreach ($payments as $payment) {
            if (! $payment instanceof Payment) {
                continue;
            }
            if (! self::isLegacyPortalImport($payment)) {
                $out[] = $payment;
            }
        }

        return $out;
    }
}
