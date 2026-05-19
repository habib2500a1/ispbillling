<?php

namespace App\Services\Bandwidth;

use App\Models\BandwidthClient;
use App\Models\BandwidthClientInvoice;
use App\Models\BandwidthClientPayment;
use Illuminate\Support\Str;

final class BandwidthClientBillingService
{
    public function generateInvoice(
        BandwidthClient $client,
        int $month,
        int $year,
        ?float $amount = null,
        ?string $notes = null,
    ): BandwidthClientInvoice {
        $amount ??= (float) $client->profile_total;

        $invoice = BandwidthClientInvoice::query()->create([
            'tenant_id' => $client->tenant_id,
            'bandwidth_client_id' => $client->id,
            'invoice_number' => $this->nextInvoiceNumber($client),
            'period_month' => $month,
            'period_year' => $year,
            'amount' => $amount,
            'amount_paid' => 0,
            'status' => 'due',
            'due_date' => now()->setDate($year, $month, 1)->endOfMonth(),
            'notes' => $notes,
        ]);

        return $invoice;
    }

    public function recordPayment(
        BandwidthClient $client,
        float $amount,
        ?BandwidthClientInvoice $invoice = null,
        string $method = 'cash',
        ?string $reference = null,
        ?string $notes = null,
    ): BandwidthClientPayment {
        $payment = BandwidthClientPayment::query()->create([
            'tenant_id' => $client->tenant_id,
            'bandwidth_client_id' => $client->id,
            'bandwidth_client_invoice_id' => $invoice?->id,
            'amount' => $amount,
            'paid_at' => now(),
            'method' => $method,
            'reference' => $reference,
            'notes' => $notes,
        ]);

        if ($invoice !== null) {
            $invoice->increment('amount_paid', $amount);
            $balance = $invoice->fresh()->balanceDue();
            $invoice->update([
                'status' => $balance <= 0.009 ? 'paid' : 'partial',
            ]);
        }

        return $payment;
    }

    private function nextInvoiceNumber(BandwidthClient $client): string
    {
        $seq = BandwidthClientInvoice::query()
            ->where('bandwidth_client_id', $client->id)
            ->count() + 1;

        $code = $client->client_code ?: ('BW'.$client->id);

        return 'BW-'.Str::upper(Str::slug($code, '')).'-'.now()->format('Ym').'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
