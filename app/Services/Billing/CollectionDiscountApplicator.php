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
}
