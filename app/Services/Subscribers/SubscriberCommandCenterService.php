<?php

namespace App\Services\Subscribers;

use App\Filament\Pages\SendSms;
use App\Filament\Resources\SupportTicketResource;
use App\Models\BandwidthUsageDaily;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Payment;
use App\Models\NotificationLog;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Billing\CustomerPrepayService;
use App\Services\Network\CustomerConnectionStatusService;
use App\Services\Network\SubscriberNetworkPathService;
use App\Services\Optical\SubscriberOnuOpsPresenter;
use App\Support\MacAddress;
use Illuminate\Support\Collection;

/**
 * Enterprise 360° command center data for the subscriber view page.
 */
final class SubscriberCommandCenterService
{
    public function __construct(
        private readonly CustomerConnectionStatusService $connectionStatus,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forCustomer(Customer $customer): array
    {
        $customer->loadMissing([
            'package:id,name,download_mbps,upload_mbps,price_monthly',
            'onuDevice.olt:id,display_name,hostname',
            'onuDevice.onuHealthScore',
            'activePppSession',
            'area:id,name',
        ]);

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $openBalance = (float) $customer->openInvoiceBalance();
        $conn = $this->connectionStatus->summary($customer);
        $onu = $customer->primaryOnu();
        $health = $this->healthScore($customer, $onu, $openBalance);
        $tickets = $this->ticketSummary($customer);
        $revenue = $this->revenueStats($customer);
        $churn = $this->churnRisk($customer, $openBalance, $tickets['open_count'], $meta);

        return [
            'header_strip' => $this->headerStrip($customer, $meta, $openBalance, $tickets, $conn),
            'kpis' => $this->kpiCards($customer, $openBalance, $tickets, $health, $revenue, $churn, $conn),
            'tickets' => $tickets,
            'live_session' => $this->liveSessionPanel($customer, $conn),
            'network_path' => app(SubscriberNetworkPathService::class)->path($customer),
            'onu_ops' => app(SubscriberOnuOpsPresenter::class)->forCustomer($customer),
            'health' => $health,
            'intelligence' => [
                'ai_summary' => $this->aiSummary($customer, $openBalance, $tickets, $health, $churn),
                'churn' => $churn,
                'revenue' => $revenue,
            ],
            'fab_actions' => $this->fabActions($customer),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $tickets
     * @param  array<string, mixed>  $conn
     * @return array<string, mixed>
     */
    private function headerStrip(Customer $customer, array $meta, float $openBalance, array $tickets, array $conn): array
    {
        $assignee = $this->resolveStaffName($meta['technician_id'] ?? $meta['collector_id'] ?? null);

        return [
            'is_vip' => ! empty($meta['tag_vip']),
            'is_corporate' => ! empty($meta['tag_corporate']),
            'open_tickets' => $tickets['open_count'],
            'due_bdt' => round($openBalance, 2),
            'online' => $customer->isPppOnline(),
            'online_label' => $customer->isPppOnline()
                ? ($conn['connection_duration'] ?? 'Online')
                : 'Offline',
            'assignee' => $assignee,
            'network_state' => (string) ($customer->network_access_state ?? 'active'),
        ];
    }

    /**
     * @param  array<string, mixed>  $tickets
     * @param  array<string, mixed>  $health
     * @param  array<string, mixed>  $revenue
     * @param  array<string, mixed>  $churn
     * @param  array<string, mixed>  $conn
     * @return list<array<string, mixed>>
     */
    private function kpiCards(
        Customer $customer,
        float $openBalance,
        array $tickets,
        array $health,
        array $revenue,
        array $churn,
        array $conn,
    ): array {
        $monthly = app(CustomerPrepayService::class)->monthlyRate($customer);

        return [
            ['label' => 'Total due', 'value' => number_format($openBalance, 0), 'meta' => 'BDT outstanding', 'icon' => 'heroicon-o-banknotes', 'tone' => $openBalance > 0 ? 'rose' : 'emerald'],
            ['label' => 'Valid until', 'value' => $customer->service_expires_at?->format('d-M-Y') ?? '—', 'meta' => $customer->isServiceExpired() ? 'Renew required' : 'Service window', 'icon' => 'heroicon-o-calendar-days', 'tone' => $customer->isServiceExpired() ? 'rose' : 'sky'],
            ['label' => 'Package', 'value' => \Illuminate\Support\Str::limit($customer->package?->name ?? '—', 22), 'meta' => $customer->package?->speedLabel() ?? '—', 'icon' => 'heroicon-o-cube', 'tone' => 'violet'],
            ['label' => 'Monthly bill', 'value' => $monthly !== null ? number_format($monthly, 0) : '—', 'meta' => 'Wallet '.number_format((float) $customer->account_balance, 0).' BDT', 'icon' => 'heroicon-o-receipt-percent', 'tone' => 'emerald'],
            ['label' => 'Open tickets', 'value' => (string) $tickets['open_count'], 'meta' => $tickets['open_count'] > 0 ? 'Needs attention' : 'All clear', 'icon' => 'heroicon-o-lifebuoy', 'tone' => $tickets['open_count'] > 0 ? 'amber' : 'emerald'],
            ['label' => 'PPPoE', 'value' => $customer->isPppOnline() ? 'Online' : 'Offline', 'meta' => $customer->isPppOnline() ? ($conn['connection_duration'] ?? 'Active') : ($conn['last_disconnect_formatted'] ?? 'Last seen'), 'icon' => 'heroicon-o-signal', 'tone' => $customer->isPppOnline() ? 'cyan' : 'gray'],
            ['label' => 'Health score', 'value' => (string) $health['score'], 'meta' => $health['label'], 'icon' => 'heroicon-o-heart', 'tone' => $health['tone']],
            ['label' => 'Lifetime paid', 'value' => number_format($revenue['lifetime_bdt'], 0), 'meta' => 'BDT collected', 'icon' => 'heroicon-o-currency-dollar', 'tone' => 'indigo'],
            ['label' => 'Churn risk', 'value' => $churn['label'], 'meta' => $churn['hint'], 'icon' => 'heroicon-o-exclamation-triangle', 'tone' => $churn['tone']],
            ['label' => 'Last SMS', 'value' => $this->lastSmsLabel($customer), 'meta' => 'Communication touchpoint', 'icon' => 'heroicon-o-chat-bubble-left-right', 'tone' => 'slate'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketSummary(Customer $customer): array
    {
        $open = SupportTicket::query()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->with('assignee:id,name')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $total = SupportTicket::query()->where('customer_id', $customer->id)->count();

        return [
            'open_count' => $open->count(),
            'total_count' => $total,
            'create_url' => SupportTicketResource::getUrl('create', ['customer_id' => $customer->id]),
            'index_url' => SupportTicketResource::getUrl('index', [
                'tableFilters' => ['customer_id' => ['value' => (string) $customer->id]],
            ]),
            'open' => $open->map(fn (SupportTicket $t): array => [
                'id' => $t->id,
                'number' => $t->ticket_number,
                'subject' => $t->subject,
                'status' => $t->statusLabel(),
                'priority' => $t->priority,
                'assignee' => $t->assignee?->name,
                'url' => SupportTicketResource::getUrl('edit', ['record' => $t]),
                'created_at' => $t->created_at?->diffForHumans(),
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $conn
     * @return array<string, mixed>
     */
    private function liveSessionPanel(Customer $customer, array $conn): array
    {
        $ppp = $customer->activePppSession;
        $meta = is_array($customer->meta) ? $customer->meta : [];

        return [
            'online' => $customer->isPppOnline(),
            'duration' => $conn['connection_duration'] ?? '—',
            'framed_ip' => $ppp?->framed_ip ?? ($meta['static_ip'] ?? '—'),
            'caller_id' => $ppp?->caller_id ? (MacAddress::normalizeColon($ppp->caller_id) ?? $ppp->caller_id) : '—',
            'started_at' => $ppp?->started_at?->format('d M Y · H:i') ?? '—',
            'bytes_in' => $ppp?->bytes_in !== null ? BandwidthUsageDaily::formatBytes((int) $ppp->bytes_in) : '—',
            'bytes_out' => $ppp?->bytes_out !== null ? BandwidthUsageDaily::formatBytes((int) $ppp->bytes_out) : '—',
            'router' => $customer->mikrotikServer?->name ?? '—',
            'last_disconnect' => $conn['last_disconnect_formatted'] ?? '—',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function healthScore(Customer $customer, ?Device $onu, float $openBalance): array
    {
        $onuScore = $onu?->onuHealthScore?->health_score;
        $optical = is_numeric($onuScore) ? (int) $onuScore : 70;

        $billing = 100;
        if ($openBalance > 0) {
            $billing = max(20, 100 - min(80, (int) round($openBalance / 50)));
        }
        if ($customer->isServiceExpired()) {
            $billing = min($billing, 30);
        }

        $connection = $customer->isPppOnline() ? 95 : 45;
        if (($customer->network_access_state ?? 'active') === 'suspended') {
            $connection = 20;
        }

        $openTickets = SupportTicket::query()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->count();
        $support = max(40, 100 - ($openTickets * 15));

        $score = (int) round($optical * 0.35 + $billing * 0.30 + $connection * 0.20 + $support * 0.15);
        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'label' => match (true) {
                $score >= 85 => 'Excellent',
                $score >= 70 => 'Good',
                $score >= 50 => 'Watch',
                default => 'At risk',
            },
            'tone' => match (true) {
                $score >= 85 => 'emerald',
                $score >= 70 => 'cyan',
                $score >= 50 => 'amber',
                default => 'rose',
            },
            'components' => [
                'optical' => $optical,
                'billing' => $billing,
                'connection' => $connection,
                'support' => $support,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function revenueStats(Customer $customer): array
    {
        $lifetime = (float) Payment::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->sum('amount');

        $monthly = app(CustomerPrepayService::class)->monthlyRate($customer) ?? 0.0;
        $monthsActive = max(1, $customer->joined_at?->diffInMonths(now()) ?? 1);
        $arpu = $monthsActive > 0 ? round($lifetime / $monthsActive, 0) : 0;

        return [
            'lifetime_bdt' => round($lifetime, 2),
            'monthly_rate' => round($monthly, 2),
            'arpu_bdt' => $arpu,
            'months_active' => $monthsActive,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function churnRisk(Customer $customer, float $openBalance, int $openTickets, array $meta): array
    {
        $score = 0;
        if ($openBalance > 500) {
            $score += 25;
        } elseif ($openBalance > 0) {
            $score += 10;
        }
        if ($customer->isServiceExpired()) {
            $score += 30;
        }
        if ($customer->status === 'suspended') {
            $score += 25;
        }
        if (! empty($meta['tag_late_payer'])) {
            $score += 15;
        }
        if ($openTickets >= 2) {
            $score += 15;
        }
        if (! $customer->isPppOnline() && $customer->status === 'active') {
            $score += 10;
        }

        $score = min(100, $score);

        return [
            'score' => $score,
            'label' => match (true) {
                $score >= 60 => 'High',
                $score >= 35 => 'Medium',
                $score >= 15 => 'Low',
                default => 'Minimal',
            },
            'tone' => match (true) {
                $score >= 60 => 'rose',
                $score >= 35 => 'amber',
                $score >= 15 => 'sky',
                default => 'emerald',
            },
            'hint' => $score >= 60 ? 'Retention action recommended' : 'Stable subscriber',
        ];
    }

    /**
     * @param  array<string, mixed>  $tickets
     * @param  array<string, mixed>  $health
     * @param  array<string, mixed>  $churn
     */
    private function aiSummary(Customer $customer, float $openBalance, array $tickets, array $health, array $churn): string
    {
        $parts = [];
        $meta = is_array($customer->meta) ? $customer->meta : [];

        if (! empty($meta['tag_vip'])) {
            $parts[] = 'VIP subscriber';
        }
        if (! empty($meta['tag_corporate'])) {
            $parts[] = 'corporate SLA account';
        }

        $parts[] = $customer->isPppOnline() ? 'PPPoE is online' : 'PPPoE is offline';
        $parts[] = 'health score '.$health['score'].'/100 ('.$health['label'].')';

        if ($openBalance > 0) {
            $parts[] = number_format($openBalance, 0).' BDT outstanding';
        }

        if ($tickets['open_count'] > 0) {
            $parts[] = $tickets['open_count'].' open support ticket(s)';
        }

        $parts[] = 'churn risk '.$churn['label'];

        if ($onu = $customer->primaryOnu()) {
            $parts[] = 'ONU on '.($onu->olt?->display_name ?? 'OLT');
        }

        return ucfirst(implode(' · ', $parts)).'.';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fabActions(Customer $customer): array
    {
        $suspended = ($customer->network_access_state ?? 'active') === 'suspended';
        $path = app(SubscriberNetworkPathService::class)->path($customer);
        $office = $path['office_access'] ?? [];
        $links = $path['links'] ?? [];
        $oneClick = $path['one_click_router'] ?? [];

        $actions = [
            ['key' => 'collect', 'label' => 'Collect', 'icon' => 'heroicon-o-banknotes', 'url' => \App\Filament\Pages\BillCollectionDesk::getUrl(['customer' => $customer->id]), 'type' => 'link'],
            ['key' => 'portal', 'label' => 'Customer portal', 'icon' => 'heroicon-o-arrow-right-on-rectangle', 'url' => route('staff.subscribers.portal-login', ['customer' => $customer->getKey()]), 'type' => 'external'],
        ];

        if (! empty($oneClick['available'])) {
            $actions[] = [
                'key' => 'router_login',
                'label' => 'Router login',
                'icon' => 'heroicon-o-bolt',
                'url' => $oneClick['url'],
                'type' => 'external',
            ];
        } elseif (! empty($office['online']) && ! empty($office['wan_admin_url'])) {
            $actions[] = [
                'key' => 'wan_router',
                'label' => 'WAN router',
                'icon' => 'heroicon-o-globe-alt',
                'url' => $office['wan_admin_url'],
                'type' => 'external',
            ];
        }

        if (! empty($links['billing_router_portal'])) {
            $actions[] = [
                'key' => 'router_portal',
                'label' => '/router billing',
                'icon' => 'heroicon-o-home',
                'url' => $links['billing_router_portal'],
                'type' => 'external',
            ];
        }

        return array_merge($actions, [
            ['key' => 'sms', 'label' => 'SMS', 'icon' => 'heroicon-o-device-phone-mobile', 'url' => SendSms::getUrl(['customer_id' => $customer->id]), 'type' => 'link'],
            ['key' => 'whatsapp', 'label' => 'WhatsApp', 'icon' => 'heroicon-o-chat-bubble-oval-left-ellipsis', 'url' => filled($customer->phone) ? 'https://wa.me/'.preg_replace('/\D+/', '', (string) $customer->phone) : '#', 'type' => 'external', 'disabled' => ! filled($customer->phone)],
            ['key' => 'ticket', 'label' => 'New ticket', 'icon' => 'heroicon-o-lifebuoy', 'url' => SupportTicketResource::getUrl('create', ['customer_id' => $customer->id]), 'type' => 'link'],
            ['key' => 'invoice', 'label' => 'Invoices', 'icon' => 'heroicon-o-document-text', 'url' => \App\Filament\Resources\InvoiceResource::getUrl('index', ['tableFilters' => ['customer_id' => ['value' => (string) $customer->id]]]), 'type' => 'link'],
            ['key' => 'net', 'label' => $suspended ? 'Net ON' : 'Net OFF', 'icon' => 'heroicon-o-signal-slash', 'type' => 'wire', 'action' => 'toggleNetworkAccess'],
        ]);
    }

    private function lastSmsLabel(Customer $customer): string
    {
        $last = NotificationLog::query()
            ->where('customer_id', $customer->id)
            ->where('channel', 'sms')
            ->orderByDesc('created_at')
            ->value('created_at');

        return $last ? $last->diffForHumans() : 'Never';
    }

    private function resolveStaffName(mixed $userId): string
    {
        if (! filled($userId)) {
            return 'Unassigned';
        }

        return User::query()->whereKey((int) $userId)->value('name') ?? 'Staff #'.$userId;
    }
}
