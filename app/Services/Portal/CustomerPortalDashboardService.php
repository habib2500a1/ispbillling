<?php

namespace App\Services\Portal;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PppSessionLog;
use App\Support\BandwidthDirection;
final class CustomerPortalDashboardService
{
    public function __construct(
        private readonly CustomerBandwidthService $bandwidth,
        private readonly CustomerOnuOpticalService $onu,
        private readonly CustomerPortalNotificationService $notifications,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(Customer $customer): array
    {
        $customer->loadMissing('package:id,name,download_mbps,upload_mbps,price_monthly');

        $live = $this->bandwidth->liveStats($customer);

        $session = PppSessionLog::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->orderByDesc('started_at')
            ->first(['id', 'started_at', 'ended_at', 'framed_ip', 'bytes_in', 'bytes_out', 'peak_rate_in_bps', 'peak_rate_out_bps', 'meta']);

        $lastSessionEndedAt = $session?->ended_at
            ?? PppSessionLog::query()
                ->where('customer_id', $customer->id)
                ->orderByDesc('started_at')
                ->value('ended_at');

        $invoiceDueQuery = Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['open', 'partial', 'draft']);

        $totalDue = (float) (Invoice::query()->getConnection()->getDriverName() === 'sqlite'
            ? $invoiceDueQuery->get(['total', 'amount_paid'])->sum(
                fn (Invoice $inv) => max(0, round((float) $inv->total - (float) $inv->amount_paid, 2))
            )
            : $invoiceDueQuery->selectRaw('COALESCE(SUM(GREATEST(0, total - amount_paid)), 0) as due')->value('due') ?? 0);

        $nextDue = Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['open', 'partial'])
            ->orderBy('due_date')
            ->first(['invoice_number', 'due_date']);

        $onuSnap = $this->onu->snapshot($customer);
        $monthly = $this->bandwidth->monthlyUsage($customer);

        return [
            'connection' => [
                'online' => $live['online'],
                'ppp_connected' => $live['online'],
                'status_label' => $live['online'] ? 'Online' : 'Offline',
                'status_color' => $live['online'] ? 'emerald' : 'slate',
                'framed_ip' => $live['framed_ip'],
                'session_uptime' => $session?->formattedDuration(),
                'session_started' => $live['session_started'],
                'last_online' => $live['online']
                    ? 'Now'
                    : ($customer->ppp_last_seen_at?->diffForHumans() ?? ($lastSessionEndedAt ? \Illuminate\Support\Carbon::parse($lastSessionEndedAt)->diffForHumans() : '—')),
                'router_status' => $live['online'] ? 'PPPoE active' : 'Disconnected',
                'onu_status' => $onuSnap['oper_status'] ?? ($onuSnap['linked'] ? 'unknown' : 'not_linked'),
            ],
            'traffic' => [
                'download_bps' => $live['download_bps'],
                'upload_bps' => $live['upload_bps'],
                'download_human' => BandwidthDirection::formatBps($live['download_bps']),
                'upload_human' => BandwidthDirection::formatBps($live['upload_bps']),
                'today_download' => $live['today_download'],
                'today_upload' => $live['today_upload'],
                'month_download' => $monthly['bytes_in'],
                'month_upload' => $monthly['bytes_out'],
                'peak_download_bps' => $monthly['peak_in_bps'],
                'peak_upload_bps' => $monthly['peak_out_bps'],
                'chart' => $live['chart'],
            ],
            'onu' => $onuSnap,
            'billing' => [
                'wallet_balance' => round((float) $customer->account_balance, 2),
                'total_due' => round($totalDue, 2),
                'next_due_date' => $nextDue?->due_date?->format('d M Y'),
                'next_invoice' => $nextDue?->invoice_number,
            ],
            'package' => $customer->package ? [
                'name' => $customer->package->name,
                'download_mbps' => $customer->package->download_mbps,
                'upload_mbps' => $customer->package->upload_mbps ?? null,
                'price_monthly' => (float) $customer->package->price_monthly,
                'expires_at' => $customer->service_expires_at?->format('d M Y'),
            ] : null,
            'notifications_count' => $this->notifications->unreadCount($customer),
        ];
    }
}
