<?php

namespace App\Services\Mobile;

use App\Models\Customer;
use App\Models\Invoice;
use App\Support\BandwidthDirection;

class CustomerMobileService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(Customer $customer): array
    {
        $customer->loadMissing('package');

        $totalDue = Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['open', 'partial', 'draft'])
            ->get()
            ->sum(fn (Invoice $inv) => max(0, round((float) $inv->total - (float) $inv->amount_paid, 2)));

        $recentBills = Invoice::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('issue_date')
            ->limit(5)
            ->get()
            ->map(fn (Invoice $inv): array => $this->invoiceSummary($inv));

        return [
            'customer' => $this->customerPayload($customer),
            'account_balance' => round((float) $customer->account_balance, 2),
            'total_due' => round($totalDue, 2),
            'recent_bills' => $recentBills,
            'bkash_enabled' => (bool) config('bkash.enabled'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function invoiceSummary(Invoice $invoice): array
    {
        $due = round((float) $invoice->total - (float) $invoice->amount_paid, 2);

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'total' => round((float) $invoice->total, 2),
            'amount_paid' => round((float) $invoice->amount_paid, 2),
            'balance_due' => $due,
            'can_pay' => $due > 0 && ! in_array($invoice->status, ['void', 'cancelled', 'paid'], true) && config('bkash.enabled'),
            'pdf_url' => route('portal.invoices.pdf', $invoice),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function customerPayload(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'customer_code' => $customer->customer_code,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'package' => $customer->package ? [
                'id' => $customer->package->id,
                'name' => $customer->package->name,
                'download_mbps' => $customer->package->download_mbps,
                'price_monthly' => (float) $customer->package->price_monthly,
            ] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    public function usagePayload(array $stats): array
    {
        return [
            'online' => $stats['online'],
            'download_bps' => $stats['download_bps'],
            'upload_bps' => $stats['upload_bps'],
            'download_human' => BandwidthDirection::formatBps($stats['download_bps'] ?? null),
            'upload_human' => BandwidthDirection::formatBps($stats['upload_bps'] ?? null),
            'total_download' => $stats['total_download'],
            'total_upload' => $stats['total_upload'],
            'today_download' => $stats['today_download'],
            'today_upload' => $stats['today_upload'],
            'framed_ip' => $stats['framed_ip'],
            'session_started' => $stats['session_started'],
            'chart' => $stats['chart'],
        ];
    }
}
