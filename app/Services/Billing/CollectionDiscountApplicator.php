<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

final class CollectionDiscountApplicator
{
    /**
     * Add collection-time discount to invoice and log on payment meta.
     */
    public static function apply(
        Invoice $invoice,
        float $discountBdt,
        Payment $payment,
        ?string $presetId = null,
        ?string $noteFragment = null,
    ): void {
        $discountBdt = round(max(0.0, $discountBdt), 2);
        if ($discountBdt <= 0) {
            return;
        }

        DB::transaction(function () use ($invoice, $discountBdt, $payment, $presetId, $noteFragment): void {
            $invoice = $invoice->fresh();
            if ($invoice === null) {
                return;
            }

            $dueBefore = $invoice->balanceDue();
            $applied = min($discountBdt, $dueBefore);
            if ($applied <= 0) {
                return;
            }

            $stamp = now()->format('Y-m-d H:i');
            $by = auth()->user()?->name ?? 'staff';
            $line = "[Collection discount {$stamp}] -{$applied} BDT by {$by}";
            if ($presetId) {
                $preset = CollectionDiscountSettings::findPreset($presetId);
                if ($preset !== null) {
                    $line .= ' ('.$preset['label'].')';
                }
            }
            if ($noteFragment) {
                $line .= ' — '.$noteFragment;
            }

            $notes = trim((string) $invoice->notes);
            $invoice->forceFill([
                'discount_amount' => round((float) $invoice->discount_amount + $applied, 2),
                'notes' => $notes === '' ? $line : $notes."\n".$line,
            ])->save();

            InvoiceCalculator::recalculate($invoice->fresh());

            $meta = $payment->meta ?? [];
            $meta['discount'] = round(((float) ($meta['discount'] ?? 0)) + $applied, 2);
            $meta['collection_discount'] = $applied;
            if ($presetId) {
                $meta['collection_discount_preset'] = $presetId;
            }
            $payment->forceFill(['meta' => $meta])->saveQuietly();
        });
    }

    /**
     * Undo collection discount applied with this payment (before void / correction).
     */
    public static function reverseFromPayment(Payment $payment): void
    {
        $applied = round((float) ($payment->meta['collection_discount'] ?? 0), 2);
        if ($applied <= 0 || $payment->invoice_id === null) {
            return;
        }

        $invoice = $payment->invoice?->fresh();
        if ($invoice === null) {
            return;
        }

        DB::transaction(function () use ($invoice, $applied, $payment): void {
            $invoice->forceFill([
                'discount_amount' => max(0.0, round((float) $invoice->discount_amount - $applied, 2)),
            ])->save();

            InvoiceCalculator::recalculate($invoice->fresh());

            $stamp = now()->format('Y-m-d H:i');
            $by = auth()->user()?->name ?? 'system';
            $notes = trim((string) $invoice->notes);
            $line = "[Collection discount reversed {$stamp}] +{$applied} BDT (void payment #{$payment->id}) by {$by}";
            $invoice->forceFill([
                'notes' => $notes === '' ? $line : $notes."\n".$line,
            ])->saveQuietly();
        });
    }
}
