<?php

namespace App\Services\Portal;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Network\CustomerConnectionStatusService;
use App\Support\BandwidthDirection;

final class CustomerPortalDashboardService
{
    public function __construct(
        private readonly CustomerBandwidthService $bandwidth,
        private readonly CustomerOnuOpticalService $onu,
        private readonly CustomerPortalNotificationService $notifications,
        private readonly CustomerConnectionStatusService $connectionStatus,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(Customer $customer): array
    {
        $customer->loadMissing('package:id,name,download_mbps,upload_mbps,price_monthly');

        $live = $this->bandwidth->liveStats($customer);

        $conn = $this->connectionStatus->summary($customer);

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
            'connection' => array_merge($conn, [
                'ppp_connected' => $live['online'],
                'status_color' => $live['online'] ? 'emerald' : 'slate',
                'session_uptime' => $conn['connection_duration'],
                'session_started' => $conn['session_started_formatted'] ?? $live['session_started'],
                'last_online' => $live['online']
                    ? 'Now'
                    : ($conn['last_disconnect_human'] !== '—' ? $conn['last_disconnect_human'] : ($conn['ppp_last_seen_human'] ?? '—')),
                'last_disconnect' => $conn['last_disconnect_formatted'],
                'router_status' => $live['online'] ? 'PPPoE active' : 'Disconnected',
                'onu_status' => $onuSnap['oper_status'] ?? ($onuSnap['linked'] ? 'unknown' : 'not_linked'),
            ]),
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
                'has_due' => $totalDue > 0,
                'can_pay' => $totalDue > 0,
                'next_due_date' => $nextDue?->due_date?->format('d M Y'),
                'next_invoice' => $nextDue?->invoice_number,
                'next_invoice_label' => $nextDue?->invoice_number
                    ? $nextDue->invoice_number.' due '.($nextDue->due_date?->format('d M Y') ?? '—')
                    : null,
                'status_tone' => $totalDue > 0 ? 'due' : 'paid',
                'cta_label' => $totalDue > 0 ? 'Pay bill' : 'View bills',
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
