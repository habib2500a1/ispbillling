<?php

namespace App\Services\Support;

use App\Filament\Pages\BillCollectionDesk;
use App\Filament\Pages\FiberPlantMap;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\SupportTicketResource;
use App\Models\Customer;
use App\Models\Device;
use App\Models\FieldVisit;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Services\Network\FiberPlantMapService;
use App\Support\CustomerStatus;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class SupportTicketWorkspaceService
{
    public function resolveCustomer(SupportTicket $ticket, mixed $formCustomerId = null): ?Customer
    {
        $customerId = $formCustomerId ?: $ticket->customer_id;

        if (! $customerId) {
            return null;
        }

        if ($ticket->relationLoaded('customer') && $ticket->customer && (int) $ticket->customer->id === (int) $customerId) {
            $ticket->customer->loadMissing(['package', 'area', 'zone', 'mikrotikServer', 'onuDevice.olt', 'lastEndedPppSession']);

            return $ticket->customer;
        }

        return Customer::query()
            ->with(['package', 'area', 'zone', 'mikrotikServer', 'onuDevice.olt', 'lastEndedPppSession'])
            ->find($customerId);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildViewBundle(SupportTicket $ticket, mixed $formCustomerId = null): array
    {
        $customer = $this->resolveCustomer($ticket, $formCustomerId);
        $c360 = $this->customer360($ticket, $customer);
        $linked = ! empty($c360['linked']);
        $live = array_merge(
            ['linked' => $linked],
            is_array($c360['live'] ?? null) ? $c360['live'] : []
        );

        return [
            'c360' => $c360,
            'timeline' => $this->timeline($ticket),
            'hints' => $this->rootCauseHints($ticket, $customer),
            'gis' => $this->gisPreview($ticket, $customer),
            'network' => $this->networkRail($c360, $live),
            'live' => $live,
            'close_offline_notice' => $this->closeOfflineNotice($live),
        ];
    }

    public function liveStatusHtml(mixed $customerId): HtmlString
    {
        if (! $customerId) {
            return new HtmlString('<span class="text-gray-500">Select a subscriber to see PPP / ONU status.</span>');
        }

        $customer = Customer::query()
            ->with(['onuDevice', 'lastEndedPppSession'])
            ->find($customerId);

        if ($customer === null) {
            return new HtmlString('<span class="text-gray-500">Subscriber not found.</span>');
        }

        $live = $this->liveStatus($customer);
        $pppClass = $live['ppp_online'] ? 'sp-live-status__badge--online' : 'sp-live-status__badge--offline';
        $pppLabel = $live['ppp_online'] ? 'Online' : 'Offline';

        $onuHtml = match ($live['onu_online']) {
            null => '<span class="sp-live-status__badge sp-live-status__badge--muted">Not mapped</span>',
            true => '<span class="sp-live-status__badge sp-live-status__badge--online">Online</span>',
            false => '<span class="sp-live-status__badge sp-live-status__badge--offline">Offline</span>',
        };

        $reason = '';
        if (! $live['ppp_online'] && filled($live['ppp_offline_reason'])) {
            $reason .= '<div class="sp-live-status__reason">'.e((string) $live['ppp_offline_reason']).'</div>';
        }
        if ($live['onu_online'] === false) {
            $reason .= '<div class="sp-live-status__reason">'.e((string) ($live['onu_offline_reason'] ?? $live['onu_status'] ?? 'unknown')).'</div>';
        }

        $logout = '<div class="sp-live-status__meta">Last logout: '.e((string) ($live['last_logout_at'] ?? '—'));
        if (filled($live['last_logout_ago'])) {
            $logout .= ' · '.e((string) $live['last_logout_ago']);
        }
        $logout .= '</div>';

        return new HtmlString(
            '<div class="sp-live-status sp-live-status--compact">'
            .'<div class="sp-live-status__grid">'
            .'<div class="sp-live-status__item"><span class="sp-live-status__label">PPP / Internet</span>'
            .'<span class="sp-live-status__badge '.$pppClass.'">'.$pppLabel.'</span>'.$reason.'</div>'
            .'<div class="sp-live-status__item"><span class="sp-live-status__label">ONU</span>'.$onuHtml.'</div>'
            .'<div class="sp-live-status__item"><span class="sp-live-status__label">Last logout / seen</span>'
            .'<span class="sp-live-status__time">'.e((string) ($live['last_logout_at'] ?? '—')).'</span>'.$logout.'</div>'
            .'</div></div>'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function customer360(SupportTicket $ticket, ?Customer $customer): array
    {
        if ($customer === null) {
            return ['linked' => false];
        }

        $onu = $customer->primaryOnu();
        $live = $this->liveStatus($customer);
        $due = $customer->openInvoiceBalance();
        $ticketCount = SupportTicket::query()->where('customer_id', $customer->id)->count();
        $lastPayment = Payment::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->orderByDesc('paid_at')
            ->first(['amount', 'paid_at', 'method']);

        return [
            'linked' => true,
            'id' => $customer->id,
            'name' => $customer->name,
            'code' => $customer->customer_code,
            'phone' => $customer->phone ?? '—',
            'status' => ucfirst((string) $customer->status),
            'package' => $customer->package?->name ?? '—',
            'area' => $customer->area?->name ?? '—',
            'zone' => $customer->zone?->name ?? '—',
            'address' => $customer->formattedAddress(),
            'ppp_online' => $live['ppp_online'],
            'ppp_login' => $customer->pppLoginName(),
            'ppp_offline_reason' => $live['ppp_offline_reason'],
            'last_logout_at' => $live['last_logout_at'],
            'last_logout_ago' => $live['last_logout_ago'],
            'onu_online' => $live['onu_online'],
            'network_access' => (string) ($customer->network_access_state ?? '—'),
            'live' => array_merge(['linked' => true], $live),
            'billing_due' => $due,
            'billing_due_fmt' => number_format($due, 0).' BDT',
            'ticket_count' => $ticketCount,
            'last_payment' => $lastPayment
                ? number_format((float) $lastPayment->amount, 0).' BDT · '.$lastPayment->paid_at?->diffForHumans()
                : '—',
            'onu' => $this->serializeOnu($onu),
            'router' => $customer->mikrotikServer?->name ?? '—',
            'urls' => [
                'profile' => CustomerResource::getUrl('view', ['record' => $customer]),
                'edit' => CustomerResource::getUrl('edit', ['record' => $customer]),
                'invoices' => InvoiceResource::getUrl('index').'?tableFilters[customer_id][value]='.$customer->id,
                'collect' => BillCollectionDesk::getUrl().'?search='.urlencode((string) $customer->customer_code),
                'tickets' => SupportTicketResource::getUrl('index').'?tableFilters[customer_id][value]='.$customer->id,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function liveStatus(Customer $customer): array
    {
        $customer->loadMissing(['onuDevice.olt', 'lastEndedPppSession']);
        $onu = $customer->primaryOnu();

        return $this->buildLiveServiceStatus($customer, $onu);
    }

    public function closeOfflineNotice(array $live): ?string
    {
        if (empty($live['linked']) || ($live['ppp_online'] ?? true)) {
            return null;
        }

        $parts = array_filter([
            $live['ppp_offline_reason'] ?? 'Subscriber PPP is offline',
            isset($live['last_logout_at']) ? 'Last logout: '.$live['last_logout_at'].' ('.$live['last_logout_ago'].')' : null,
        ]);

        return 'Subscriber is offline. You can still close after documenting the fix. '.implode(' · ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLiveServiceStatus(Customer $customer, ?Device $onu): array
    {
        $pppOnline = $customer->isPppOnline();
        $lastLogout = $customer->lastEndedPppSession?->ended_at ?? $customer->ppp_last_seen_at;
        $onuOnline = null;
        $onuStatus = null;
        $onuOfflineReason = null;

        if ($onu !== null) {
            $onuStatus = (string) ($onu->onu_oper_status ?? 'unknown');
            $oper = strtolower($onuStatus);
            $onuOnline = in_array($oper, ['online', 'active', 'up', 'working'], true);
            $onuOfflineReason = filled($onu->offline_reason) ? (string) $onu->offline_reason : null;
        }

        return [
            'ppp_online' => $pppOnline,
            'ppp_offline_reason' => $pppOnline ? null : $this->resolvePppOfflineReason($customer),
            'last_logout_at' => $lastLogout?->format('d M Y, h:i A'),
            'last_logout_ago' => $lastLogout?->diffForHumans() ?? 'Never',
            'onu_online' => $onuOnline,
            'onu_status' => $onuStatus,
            'onu_offline_reason' => $onuOfflineReason,
            'onu_last_polled' => $onu?->last_polled_at?->diffForHumans(),
        ];
    }

    private function resolvePppOfflineReason(Customer $customer): string
    {
        $lastSeen = $customer->ppp_last_seen_at ?? $customer->lastEndedPppSession?->ended_at;

        return match (true) {
            $customer->status === CustomerStatus::SUSPENDED => 'Suspended by billing',
            $customer->isServiceExpired() => 'Service expired / billing due',
            $customer->openInvoiceBalance() > 0.009 => 'Due balance — line may be restricted',
            $lastSeen !== null && $lastSeen->greaterThan(now()->subMinutes(30)) => 'Recently disconnected',
            $lastSeen === null => 'Never came online',
            default => 'Offline — check router, PPP secret, or NAS',
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeOnu(?Device $onu): ?array
    {
        if ($onu === null) {
            return null;
        }

        $rx = $onu->rx_power_dbm !== null ? round((float) $onu->rx_power_dbm, 2) : null;
        $oper = strtolower((string) ($onu->onu_oper_status ?? ''));
        $onuOnline = in_array($oper, ['online', 'active', 'up', 'working'], true);

        return [
            'serial' => $onu->serial_number ?? '—',
            'status' => (string) ($onu->onu_oper_status ?? 'unknown'),
            'online' => $onuOnline,
            'offline_reason' => filled($onu->offline_reason) ? (string) $onu->offline_reason : null,
            'last_polled' => $onu->last_polled_at?->diffForHumans(),
            'rx_dbm' => $rx,
            'olt' => $onu->olt?->adminLabel() ?? '—',
            'pon' => $onu->pon_no !== null ? 'PON '.$onu->pon_no : '—',
        ];
    }

    /**
     * @return list<array{at: string, label: string, detail: string, tone: string}>
     */
    private function timeline(SupportTicket $ticket): array
    {
        $ticket->loadMissing(['messages.user', 'messages.customer', 'fieldVisits.assignee', 'assignee']);

        $events = [[
            'at' => $ticket->created_at?->toIso8601String() ?? '',
            'label' => 'Ticket created',
            'detail' => $ticket->channelLabel().' · '.$ticket->subject,
            'tone' => 'created',
        ]];

        if ($ticket->assignee) {
            $events[] = [
                'at' => $ticket->updated_at?->toIso8601String() ?? '',
                'label' => 'Assigned',
                'detail' => 'Technician: '.$ticket->assignee->name,
                'tone' => 'assigned',
            ];
        }

        if ($ticket->escalation_level > 0 && $ticket->escalated_at) {
            $events[] = [
                'at' => $ticket->escalated_at->toIso8601String(),
                'label' => 'Escalated',
                'detail' => 'Level '.$ticket->escalation_level,
                'tone' => 'escalated',
            ];
        }

        foreach ($ticket->fieldVisits as $visit) {
            if ($visit->scheduled_at) {
                $events[] = [
                    'at' => $visit->scheduled_at->toIso8601String(),
                    'label' => 'Field visit scheduled',
                    'detail' => ($visit->assignee?->name ?? 'Technician').' · '.(FieldVisit::STATUSES[$visit->status] ?? $visit->status),
                    'tone' => 'field',
                ];
            }
            if ($visit->completed_at) {
                $events[] = [
                    'at' => $visit->completed_at->toIso8601String(),
                    'label' => 'Field visit completed',
                    'detail' => Str::limit((string) ($visit->report ?? ''), 120),
                    'tone' => 'resolved',
                ];
            }
        }

        foreach ($ticket->messages as $message) {
            $events[] = [
                'at' => $message->created_at?->toIso8601String() ?? '',
                'label' => $message->is_internal ? 'Internal note' : ($message->customer_id ? 'Customer reply' : 'Staff reply'),
                'detail' => Str::limit(strip_tags((string) $message->body), 120),
                'tone' => $message->is_internal ? 'internal' : 'message',
            ];
        }

        if ($ticket->resolved_at) {
            $events[] = [
                'at' => $ticket->resolved_at->toIso8601String(),
                'label' => 'Resolved',
                'detail' => 'Ticket marked resolved',
                'tone' => 'resolved',
            ];
        }

        if ($ticket->closed_at) {
            $events[] = [
                'at' => $ticket->closed_at->toIso8601String(),
                'label' => 'Closed',
                'detail' => 'Ticket closed',
                'tone' => 'closed',
            ];
        }

        usort($events, fn (array $a, array $b): int => strcmp($a['at'], $b['at']));

        return $events;
    }

    /**
     * @return list<array{title: string, cause: string, confidence: string}>
     */
    private function rootCauseHints(SupportTicket $ticket, ?Customer $customer): array
    {
        if ($customer === null) {
            return [];
        }

        $customer->loadMissing(['onuDevice.olt']);
        $hints = [];
        $onu = $customer->primaryOnu();
        $online = $customer->isPppOnline();

        if ($customer->status === 'suspended') {
            $hints[] = [
                'title' => 'Account suspended',
                'cause' => 'Service may be blocked by billing — verify payment and grace status.',
                'confidence' => 'high',
            ];
        }

        if (! $online && $onu === null) {
            $hints[] = [
                'title' => 'Customer offline',
                'cause' => 'No ONU mapped — check PPP credentials, router, or RADIUS session.',
                'confidence' => 'medium',
            ];
        }

        if (! $online && $onu !== null) {
            $onuStatus = strtolower((string) ($onu->onu_oper_status ?? ''));
            if (in_array($onuStatus, ['offline', 'down', 'los', 'dying_gasp'], true)) {
                $hints[] = [
                    'title' => 'ONU offline',
                    'cause' => 'Likely ONU power failure, dead ONU, or last-mile drop fiber fault.',
                    'confidence' => 'high',
                ];
            } elseif ($online === false) {
                $hints[] = [
                    'title' => 'PPPoE offline · ONU up',
                    'cause' => 'Check router/CPE, PPP secret, or MikroTik/NAS authentication.',
                    'confidence' => 'medium',
                ];
            }
        }

        if ($onu !== null && $onu->rx_power_dbm !== null && (float) $onu->rx_power_dbm <= -27) {
            $hints[] = [
                'title' => 'Weak optical signal',
                'cause' => 'Dirty connector, bent patch cord, or splitter port degradation.',
                'confidence' => 'high',
            ];
        }

        if (in_array($ticket->issue_type, ['outage', 'connection', 'speed'], true) && ! $online) {
            $hints[] = [
                'title' => 'Area / PON impact',
                'cause' => 'If multiple subscribers on same PON are down — suspect fiber cut or OLT port fault.',
                'confidence' => 'medium',
            ];
        }

        return array_slice($hints, 0, 4);
    }

    /**
     * @return array<string, mixed>
     */
    private function gisPreview(SupportTicket $ticket, ?Customer $customer): array
    {
        if ($customer === null) {
            return ['available' => false, 'map_url' => FiberPlantMap::getUrl()];
        }

        $lat = data_get($customer->meta, 'gps_lat');
        $lng = data_get($customer->meta, 'gps_lng');
        $visit = $ticket->fieldVisits()->whereNotNull('latitude')->latest('id')->first();

        if (! is_numeric($lat) && $visit) {
            $lat = $visit->latitude;
            $lng = $visit->longitude;
        }

        $trace = [];
        try {
            $trace = app(FiberPlantMapService::class)->traceForCustomerId((int) $customer->id);
        } catch (\Throwable $e) {
            report($e);
        }

        $mapUrl = FiberPlantMap::getUrl();
        if (is_numeric($lat) && is_numeric($lng)) {
            $mapUrl .= '?'.http_build_query(['lat' => $lat, 'lng' => $lng, 'focus' => 'customer:'.$customer->id]);
        }

        return [
            'available' => is_numeric($lat) && is_numeric($lng),
            'lat' => is_numeric($lat) ? (float) $lat : null,
            'lng' => is_numeric($lng) ? (float) $lng : null,
            'address' => $customer->formattedAddress(),
            'trace_found' => (bool) ($trace['found'] ?? false),
            'trace_length_m' => (int) ($trace['total_length_m'] ?? 0),
            'navigate_url' => (is_numeric($lat) && is_numeric($lng))
                ? 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($lat.','.$lng)
                : null,
            'map_url' => $mapUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $c360
     * @param  array<string, mixed>  $live
     * @return array<string, mixed>
     */
    private function networkRail(array $c360, array $live): array
    {
        if (empty($c360['linked'])) {
            return [];
        }

        return [
            'ppp_online' => $live['ppp_online'],
            'ppp_login' => $c360['ppp_login'],
            'ppp_offline_reason' => $live['ppp_offline_reason'],
            'last_logout_at' => $live['last_logout_at'],
            'last_logout_ago' => $live['last_logout_ago'],
            'network_access' => $c360['network_access'],
            'router' => $c360['router'],
            'onu' => $c360['onu'],
            'onu_online' => $live['onu_online'],
            'onu_offline_reason' => $live['onu_offline_reason'],
        ];
    }
}
