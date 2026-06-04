<?php

namespace App\Services\Resellers;

use App\Models\Invoice;
use App\Models\Reseller;
use App\Services\Billing\InvoiceCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ResellerInvoiceAdjustmentService
{
    /**
     * Apply discount or full waive on a reseller-owned customer invoice.
     *
     * @return array{invoice: Invoice, previous_discount: float, new_discount: float}
     */
    public function applyAdjustment(
        Reseller $reseller,
        Invoice $invoice,
        float $discountAmount,
        ?string $reason = null,
        bool $waiveFull = false,
    ): array {
        $invoice->loadMissing('customer');
        $customer = $invoice->customer;
        if ($customer === null || (int) $customer->reseller_id !== (int) $reseller->id) {
            throw ValidationException::withMessages(['invoice' => 'Invoice does not belong to this partner.']);
        }

        if (in_array($invoice->status, ['void', 'cancelled', 'paid'], true)) {
            throw ValidationException::withMessages(['invoice' => 'Cannot adjust a '.$invoice->status.' invoice.']);
        }

        $balance = max(0, round((float) $invoice->total - (float) $invoice->amount_paid, 2));
        if ($balance <= 0 && ! $waiveFull) {
            throw ValidationException::withMessages(['invoice' => 'Nothing due on this invoice.']);
        }

        return DB::transaction(function () use ($reseller, $invoice, $discountAmount, $reason, $waiveFull, $balance): array {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $previous = (float) $locked->discount_amount;

            if ($waiveFull) {
                $coupon = (float) ($locked->coupon_discount_amount ?? 0);
                $newDiscount = max(0, round(
                    (float) $locked->subtotal - $coupon
                    + (float) $locked->tax_amount
                    + (float) $locked->sd_amount
                    - (float) $locked->amount_paid,
                    2,
                ));
            } else {
                $newDiscount = round($previous + $discountAmount, 2);
            }

            $maxPercent = (float) config('reseller_billing.max_invoice_discount_percent', 100);
            $subtotalBase = max(0.01, (float) $locked->subtotal);
            if ($maxPercent < 100 && ($newDiscount / $subtotalBase) * 100 > $maxPercent + 0.01) {
                throw ValidationException::withMessages([
                    'discount' => 'Discount cannot exceed '.number_format($maxPercent, 0).'% of subtotal.',
                ]);
            }

            if ($newDiscount < 0) {
                throw ValidationException::withMessages(['discount' => 'Invalid discount amount.']);
            }

            $locked->discount_amount = $newDiscount;
            $meta = is_array($locked->meta) ? $locked->meta : [];
            $meta['reseller_adjustments'] = array_merge($meta['reseller_adjustments'] ?? [], [[
                'at' => now()->toIso8601String(),
                'reseller_id' => $reseller->id,
                'added' => round($newDiscount - $previous, 2),
                'reason' => $reason,
                'waive_full' => $waiveFull,
            ]]);
            $locked->meta = $meta;
            $locked->saveQuietly();

            InvoiceCalculator::recalculate($locked->fresh(['items', 'customer.package']));

            $fresh = $locked->fresh(['customer', 'items']);
            app(ResellerPortalActivityLogger::class)->log($reseller, 'invoice.adjust', $fresh, [
                'discount_added' => round($newDiscount - $previous, 2),
                'reason' => $reason,
            ]);

            return [
                'invoice' => $fresh,
                'previous_discount' => $previous,
                'new_discount' => $newDiscount,
            ];
        });
    }
}
