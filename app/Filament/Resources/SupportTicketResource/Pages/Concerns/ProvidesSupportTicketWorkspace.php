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

    /**
     * @return array<string, mixed>
     */
    public function getCustomer360(): array
    {
        $ticket = $this->workspaceTicket();
        $customer = $ticket->customer;

        if ($customer === null) {
            return ['linked' => false];
        }

        $customer->loadMissing(['package', 'area', 'zone', 'mikrotikServer', 'onuDevice.olt']);

        $onu = $customer->primaryOnu();
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
            'ppp_online' => $customer->isPppOnline(),
            'ppp_login' => $customer->pppLoginName(),
            'network_access' => (string) ($customer->network_access_state ?? '—'),
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

        return [
            'serial' => $onu->serial_number ?? '—',
            'status' => (string) ($onu->onu_oper_status ?? 'unknown'),
            'rx_dbm' => $rx,
            'olt' => $onu->olt?->adminLabel() ?? '—',
            'pon' => $onu->pon_no !== null ? 'PON '.$onu->pon_no : '—',
        ];
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
        $customer = $ticket->customer;

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
        $customer = $ticket->customer;

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

        return [
            'ppp_online' => $c360['ppp_online'],
            'ppp_login' => $c360['ppp_login'],
            'network_access' => $c360['network_access'],
            'router' => $c360['router'],
            'onu' => $c360['onu'],
        ];
    }
}
