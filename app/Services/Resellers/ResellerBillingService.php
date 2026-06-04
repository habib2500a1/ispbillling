<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use App\Models\ResellerInvoice;
use Illuminate\Support\Facades\DB;

final class ResellerBillingService
{
    /**
     * @param  list<array{description: string, amount: float, qty?: int}>  $lines
     */
    public function createInvoice(
        Reseller $reseller,
        array $lines,
        ?\DateTimeInterface $dueDate = null,
        ?string $notes = null,
    ): ResellerInvoice {
        $subtotal = 0.0;
        $normalized = [];
        foreach ($lines as $line) {
            $qty = (int) ($line['qty'] ?? 1);
            $amount = round((float) $line['amount'] * $qty, 2);
            $subtotal += $amount;
            $normalized[] = [
                'description' => (string) $line['description'],
                'qty' => $qty,
                'unit_amount' => (float) $line['amount'],
                'amount' => $amount,
            ];
        }

        return ResellerInvoice::query()->create([
            'tenant_id' => $reseller->tenant_id,
            'reseller_id' => $reseller->id,
            'invoice_number' => ResellerInvoice::generateNumber((int) $reseller->tenant_id),
            'subtotal' => $subtotal,
            'tax' => 0,
            'total' => $subtotal,
            'amount_paid' => 0,
            'status' => ResellerInvoice::STATUS_OPEN,
            'issue_date' => now()->toDateString(),
            'due_date' => $dueDate?->format('Y-m-d'),
            'notes' => $notes,
            'line_items' => $normalized,
        ]);
    }

    public function recordPayment(ResellerInvoice $invoice, float $amount): ResellerInvoice
    {
        return DB::transaction(function () use ($invoice, $amount): ResellerInvoice {
            $invoice = ResellerInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $newPaid = (float) $invoice->amount_paid + $amount;
            $status = $newPaid >= (float) $invoice->total
                ? ResellerInvoice::STATUS_PAID
                : ($newPaid > 0 ? ResellerInvoice::STATUS_PARTIAL : $invoice->status);

            $invoice->update([
                'amount_paid' => $newPaid,
                'status' => $status,
            ]);

            return $invoice->fresh();
        });
    }

    public function outstandingDue(Reseller $reseller): float
    {
        return (float) ResellerInvoice::query()
            ->where('reseller_id', $reseller->id)
            ->whereIn('status', [ResellerInvoice::STATUS_OPEN, ResellerInvoice::STATUS_PARTIAL])
            ->get()
            ->sum(fn (ResellerInvoice $inv) => $inv->balanceDue());
    }
}
