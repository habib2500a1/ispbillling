<?php

namespace App\Filament\Resources\SupportTicketResource\Pages\Concerns;

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
use Illuminate\Support\Str;

/**
 * Read-only ticket workspace data for smart ticket UI (no workflow changes).
 */
trait ProvidesSupportTicketWorkspace
{
    protected function workspaceTicket(): SupportTicket
    {
        /** @var SupportTicket $record */
        $record = $this->record;

        return $record;
    }

    protected function workspaceCustomer(): ?Customer
    {
        $customerId = null;

        if (property_exists($this, 'data') && is_array($this->data ?? null)) {
            $customerId = $this->data['customer_id'] ?? null;
        }

        if ($customerId) {
            return Customer::query()
                ->with(['package', 'area', 'zone', 'mikrotikServer', 'onuDevice.olt', 'lastEndedPppSession'])
                ->find($customerId);
        }

        $ticket = $this->workspaceTicket();

        return $ticket->customer;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCustomer360(): array
    {
        $customer = $this->workspaceCustomer();

        if ($customer === null) {
            return ['linked' => false];
        }

        $customer->loadMissing(['package', 'area', 'zone', 'mikrotikServer', 'onuDevice.olt', 'lastEndedPppSession']);

        $onu = $customer->primaryOnu();
        $live = $this->buildLiveServiceStatus($customer, $onu);
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
            'live' => $live,
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
     * @return array<string, mixed>
     */
    public function getLiveServiceStatus(): array
    {
        $customer = $this->workspaceCustomer();

        if ($customer === null) {
            return ['linked' => false];
        }

        $customer->loadMissing(['onuDevice.olt', 'lastEndedPppSession']);
        $onu = $customer->primaryOnu();

        return array_merge(['linked' => true], $this->buildLiveServiceStatus($customer, $onu));
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

    public function canCloseTicket(): bool
    {
        $ticket = $this->workspaceTicket();

        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            return true;
        }

        $issueType = $this->data['issue_type'] ?? $ticket->issue_type;

        if (! in_array($issueType, ['connection', 'speed', 'outage', 'equipment'], true)) {
            return true;
        }

        $customer = $this->workspaceCustomer();
        if ($customer === null) {
            return true;
        }

        return $customer->isPppOnline();
    }

    public function getCloseBlockReason(): ?string
    {
        if ($this->canCloseTicket()) {
            return null;
        }

        $live = $this->getLiveServiceStatus();
        $parts = array_filter([
            $live['ppp_offline_reason'] ?? 'Subscriber PPP is offline',
            isset($live['last_logout_at']) ? 'Last logout: '.$live['last_logout_at'].' ('.$live['last_logout_ago'].')' : null,
        ]);

        return 'Connection tickets can only be resolved/closed when the subscriber is online. '.implode(' · ', $parts);
    }

    /**
     * @return list<array{at: string, label: string, detail: string, tone: string}>
     */
    public function getTicketTimeline(): array
    {
        $ticket = $this->workspaceTicket();
        $ticket->loadMissing(['messages.user', 'messages.customer', 'fieldVisits.assignee', 'assignee']);

        $events = [];

        $events[] = [
            'at' => $ticket->created_at?->toIso8601String() ?? '',
            'label' => 'Ticket created',
            'detail' => $ticket->channelLabel().' · '.$ticket->subject,
            'tone' => 'created',
        ];

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
    public function getRootCauseHints(): array
    {
        $ticket = $this->workspaceTicket();
        $customer = $this->workspaceCustomer();

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
    public function getGisPreview(): array
    {
        $ticket = $this->workspaceTicket();
        $customer = $this->workspaceCustomer();

        if ($customer === null) {
            return ['available' => false];
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
     * @return array<string, mixed>
     */
    public function getNetworkRail(): array
    {
        $c360 = $this->getCustomer360();

        if (empty($c360['linked'])) {
            return [];
        }

        $live = $c360['live'] ?? $this->getLiveServiceStatus();

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
