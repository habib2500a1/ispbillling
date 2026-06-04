<?php

namespace App\Services\Resellers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Reseller;
use App\Services\Billing\InvoiceCalculator;
use Illuminate\Validation\ValidationException;

final class ResellerInvoiceLineService
{
    public function assertCanEdit(Reseller $reseller, Invoice $invoice): void
    {
        $invoice->loadMissing('customer');
        if ($invoice->customer === null || (int) $invoice->customer->reseller_id !== (int) $reseller->id) {
            throw ValidationException::withMessages(['invoice' => 'Invoice not found.']);
        }

        if (in_array($invoice->status, ['void', 'cancelled', 'paid'], true)) {
            throw ValidationException::withMessages(['invoice' => 'Cannot edit lines on a '.$invoice->status.' invoice.']);
        }
    }

    public function updateLine(
        Reseller $reseller,
        Invoice $invoice,
        InvoiceItem $item,
        float $unitPrice,
        ?string $description = null,
    ): Invoice {
        $this->assertCanEdit($reseller, $invoice);

        if ((int) $item->invoice_id !== (int) $invoice->id) {
            throw ValidationException::withMessages(['item' => 'Line does not belong to this invoice.']);
        }

        if ($unitPrice < 0) {
            throw ValidationException::withMessages(['unit_price' => 'Price cannot be negative.']);
        }

        $item->unit_price = round($unitPrice, 2);
        if ($description !== null) {
            $item->description = trim($description);
        }
        $item->save();

        $meta = is_array($invoice->meta) ? $invoice->meta : [];
        $meta['reseller_line_edits'] = array_merge($meta['reseller_line_edits'] ?? [], [[
            'at' => now()->toIso8601String(),
            'item_id' => $item->id,
            'unit_price' => $item->unit_price,
        ]]);
        $invoice->meta = $meta;
        $invoice->saveQuietly();

        InvoiceCalculator::recalculate($invoice->fresh(['items', 'customer.package']));

        app(ResellerPortalActivityLogger::class)->log($reseller, 'invoice.line_edit', $invoice->fresh(), [
            'item_id' => $item->id,
            'unit_price' => $item->unit_price,
        ]);

        return $invoice->fresh(['items']);
    }

    public function addAdjustmentLine(
        Reseller $reseller,
        Invoice $invoice,
        string $description,
        float $amount,
    ): Invoice {
        $this->assertCanEdit($reseller, $invoice);

        $sort = (int) $invoice->items()->max('sort_order') + 1;

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'item_type' => 'adjustment',
            'description' => $description,
            'quantity' => 1,
            'unit_price' => round($amount, 2),
            'line_total' => round($amount, 2),
            'sort_order' => $sort,
            'meta' => ['reseller_added' => true],
        ]);

        InvoiceCalculator::recalculate($invoice->fresh(['items', 'customer.package']));

        app(ResellerPortalActivityLogger::class)->log($reseller, 'invoice.line_add', $invoice->fresh(), [
            'description' => $description,
            'amount' => $amount,
        ]);

        return $invoice->fresh(['items']);
    }
}
