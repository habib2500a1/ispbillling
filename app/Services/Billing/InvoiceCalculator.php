<?php

namespace App\Services\Billing;

use App\Models\Invoice;

class InvoiceCalculator
{
    public static function recalculate(Invoice $invoice): void
    {
        if (in_array($invoice->status, ['void', 'cancelled'], true)) {
            return;
        }

        $invoice->load(['items', 'customer.package']);

        if ($invoice->items->isEmpty()) {
            static::syncStatusFromAmounts($invoice);
            $invoice->saveQuietly();

            return;
        }

        $subtotal = (float) $invoice->items->sum('line_total');
        $invoice->subtotal = round($subtotal, 2);

        $package = $invoice->customer?->package;
        $vatPercent = (float) ($package?->vat_percent ?? 0);
        $sdPercent = (float) ($package?->sd_percent ?? 0);
        $whtPercent = (float) ($package?->withholding_percent ?? 0);

        $discount = (float) $invoice->discount_amount;
        $couponDisc = (float) ($invoice->coupon_discount_amount ?? 0);

        $netAfterDiscounts = max(0.0, round((float) $invoice->subtotal - $discount - $couponDisc, 2));
        $taxAmount = round($netAfterDiscounts * ($vatPercent / 100), 2);
        $invoice->tax_amount = $taxAmount;
        $invoice->sd_amount = round($netAfterDiscounts * ($sdPercent / 100), 2);
        $invoice->withholding_amount = round($netAfterDiscounts * ($whtPercent / 100), 2);

        $invoice->total = max(0, round(
            (float) $invoice->subtotal - $discount - $couponDisc
            + (float) $invoice->tax_amount + (float) $invoice->sd_amount,
            2
        ));

        $paid = (float) $invoice->amount_paid;
        if ($invoice->total <= 0) {
            $invoice->status = 'draft';
        } elseif ($paid >= $invoice->total) {
            $invoice->status = 'paid';
        } elseif ($paid > 0) {
            $invoice->status = 'partial';
        } elseif (in_array($invoice->status, ['paid', 'partial'], true)) {
            $invoice->status = 'open';
        }

        $invoice->saveQuietly();
    }

    /**
     * When there are no line items, keep stored monetary fields and only align status with paid/total.
     */
    private static function syncStatusFromAmounts(Invoice $invoice): void
    {
        $total = (float) $invoice->total;
        $paid = (float) $invoice->amount_paid;

        if ($total <= 0) {
            return;
        }

        if ($paid >= $total) {
            $invoice->status = 'paid';
        } elseif ($paid > 0) {
            $invoice->status = 'partial';
        } elseif (in_array($invoice->status, ['paid', 'partial'], true)) {
            $invoice->status = 'open';
        }
    }
}
