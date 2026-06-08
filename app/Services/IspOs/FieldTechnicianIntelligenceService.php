<?php

namespace App\Services\IspOs;

use App\Filament\Pages\FiberPlantMap;
use App\Filament\Pages\FaultManagementHub;
use App\Filament\Resources\SupportTicketResource;
use App\Models\Customer;
use App\Models\Device;
use App\Models\FieldVisit;
use App\Models\SignalAlert;
use App\Models\StoreDeviceLoan;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\SupportTicketWorkspaceService;
use App\Support\SafeCache;
use App\Support\TenantResolver;
use Illuminate\Support\Str;

/**
 * Read-only field technician dashboard metrics (UI only — no workflow changes).
 */
final class FieldTechnicianIntelligenceService
{
    public function __construct(
        private readonly FaultManagementService $faults,
        private readonly SupportTicketWorkspaceService $workspace,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function metrics(?int $userId = null, ?int $tenantId = null): array
    {
        $userId = $userId ?? (int) auth()->id();
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        return SafeCache::remember(
            'field_tech_intel:'.$tenantId.':'.$userId,
            now()->addSeconds(60),
            fn (): array => $this->build($userId, $tenantId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function build(int $userId, int $tenantId): array
    {
        $tickets = $this->assignedTickets($userId, $tenantId);
        $visits = $this->assignedVisits($userId, $tenantId);
        $faultPayload = $this->faults->payload($tenantId);
        $tasks = $this->taskQueues($tickets, $visits);
        $nextVisit = $visits[0] ?? null;
        $assets = $this->assignedAssets($userId, $tenantId);
        $weakSignals = (int) SignalAlert::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('resolved_at')
            ->count();

        return [
            'technician' => [
                'id' => $userId,
                'name' => User::query()->find($userId)?->name ?? 'Technician',
            ],
            'kpis' => [
                'assigned_tickets' => count(array_filter($tickets, fn (array $t): bool => ! in_array($t['status'], ['resolved', 'closed'], true))),
                'visits_today' => count(array_filter($visits, fn (array $v): bool => ($v['is_today'] ?? false))),
                'pending_tasks' => $tasks['assigned']['count'] + $tasks['new']['count'],
                'completed_today' => $tasks['completed']['count'],
                'devices_out' => count($assets),
                'nearby_faults' => min(5, (int) ($faultPayload['summary']['active'] ?? 0)),
                'weak_signals' => $weakSignals,
                'route_visits' => count($visits),
            ],
            'next_visit' => $nextVisit,
            'visits' => $visits,
            'tickets' => $tickets,
            'tasks' => $tasks,
            'alerts' => $this->smartAlerts($faultPayload, $weakSignals, $tasks, $tenantId),
            'assets' => $assets,
            'links' => [
                'map' => FiberPlantMap::getUrl(),
                'faults' => FaultManagementHub::getUrl(),
                'gis_search' => FiberPlantMap::getUrl(),
            ],
            'refreshed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query, int $userId, ?int $tenantId = null, int $limit = 20): array
    {
        $q = trim($query);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $like = '%'.$q.'%';
        $results = [];

        $tickets = SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('assigned_to', $userId)
            ->where(function ($builder) use ($like): void {
                $builder->where('ticket_number', 'like', $like)
                    ->orWhere('subject', 'like', $like);
            })
            ->limit(8)
            ->get(['id', 'ticket_number', 'subject', 'status']);

        foreach ($tickets as $ticket) {
            $results[] = [
                'type' => 'ticket',
                'label' => '#'.$ticket->ticket_number,
                'sub' => $ticket->subject,
                'url' => SupportTicketResource::getUrl('edit', ['record' => $ticket]),
            ];
        }

        $customers = Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($builder) use ($like): void {
                $builder->where('name', 'like', $like)
                    ->orWhere('customer_code', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('address', 'like', $like)
                    ->orWhere('ppp_username', 'like', $like);
            })
            ->limit(8)
            ->get(['id', 'name', 'customer_code', 'phone']);

        foreach ($customers as $customer) {
            $results[] = [
                'type' => 'customer',
                'label' => $customer->name,
                'sub' => $customer->customer_code.' · '.$customer->phone,
                'url' => SupportTicketResource::getUrl('index').'?tableSearch='.urlencode((string) $customer->customer_code),
            ];
        }

        $devices = Device::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($builder) use ($like): void {
                $builder->where('serial_number', 'like', $like)
                    ->orWhere('mac_address', 'like', $like)
                    ->orWhere('display_name', 'like', $like);
            })
            ->limit(6)
            ->get(['id', 'type', 'serial_number', 'display_name']);

        foreach ($devices as $device) {
            $results[] = [
                'type' => $device->type === 'olt' ? 'olt' : 'onu',
                'label' => $device->display_name ?: $device->serial_number,
                'sub' => strtoupper((string) $device->type).' · '.$device->serial_number,
                'url' => $device->type === 'olt'
                    ? \App\Filament\Pages\OltHub::getUrl()
                    : \App\Filament\Pages\OpticalMonitoringHub::getUrl(),
            ];
        }

        return array_slice($results, 0, $limit);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function customerBundle(int $ticketId): ?array
    {
        $ticket = SupportTicket::query()->with(['customer.package', 'customer.area'])->find($ticketId);
        if ($ticket === null) {
            return null;
        }

        $bundle = $this->workspace->buildViewBundle($ticket);

        return [
            'c360' => $bundle['c360'] ?? [],
            'hints' => $bundle['hints'] ?? [],
            'gis' => $bundle['gis'] ?? [],
            'timeline' => array_slice($bundle['timeline'] ?? [], 0, 8),
            'ticket' => [
                'id' => $ticket->id,
                'number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'url' => SupportTicketResource::getUrl('edit', ['record' => $ticket]),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function assignedTickets(int $userId, int $tenantId): array
    {
        return SupportTicket::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('assigned_to', $userId)
            ->with(['customer:id,name,customer_code,phone,address'])
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get()
            ->map(fn (SupportTicket $t): array => $this->serializeTicket($t))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function assignedVisits(int $userId, int $tenantId): array
    {
        return FieldVisit::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('assigned_to', $userId)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->with(['ticket.customer:id,name,customer_code,phone,address,meta'])
            ->orderBy('scheduled_at')
            ->limit(25)
            ->get()
            ->map(fn (FieldVisit $v): array => $this->serializeVisit($v))
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $tickets
     * @param  list<array<string, mixed>>  $visits
     * @return array<string, array{count: int, items: list<array<string, mixed>>}>
     */
    private function taskQueues(array $tickets, array $visits): array
    {
        $open = array_values(array_filter($tickets, fn (array $t): bool => $t['status'] === 'open'));
        $assigned = array_values(array_filter($tickets, fn (array $t): bool => in_array($t['status'], ['open', 'pending'], true)));
        $inProgress = array_values(array_filter($tickets, fn (array $t): bool => $t['status'] === 'in_progress'));
        $completed = array_values(array_filter($tickets, fn (array $t): bool => in_array($t['status'], ['resolved', 'closed'], true)
            && ($t['resolved_today'] ?? false)));
        $escalated = array_values(array_filter($tickets, fn (array $t): bool => in_array($t['priority'], ['critical', 'high'], true)
            && ! in_array($t['status'], ['resolved', 'closed'], true)));

        $visitTasks = array_map(fn (array $v): array => array_merge($v, ['kind' => 'visit']), $visits);

        return [
            'new' => ['count' => count($open), 'items' => array_slice($open, 0, 10)],
            'assigned' => ['count' => count($assigned) + count($visits), 'items' => array_slice(array_merge($assigned, $visitTasks), 0, 10)],
            'in_progress' => ['count' => count($inProgress), 'items' => array_slice($inProgress, 0, 10)],
            'completed' => ['count' => count($completed), 'items' => array_slice($completed, 0, 10)],
            'escalated' => ['count' => count($escalated), 'items' => array_slice($escalated, 0, 10)],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function assignedAssets(int $userId, int $tenantId): array
    {
        return StoreDeviceLoan::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('issued_by', $userId)
            ->where('status', StoreDeviceLoan::STATUS_ISSUED)
            ->with(['device:id,serial_number,type,display_name', 'customer:id,name,customer_code'])
            ->orderByDesc('issued_at')
            ->limit(15)
            ->get()
            ->map(fn (StoreDeviceLoan $loan): array => [
                'id' => $loan->id,
                'device' => $loan->device?->display_name ?: $loan->device?->serial_number ?? 'Device',
                'serial' => $loan->device?->serial_number ?? '—',
                'type' => $loan->device?->type ?? 'onu',
                'customer' => $loan->customer?->name ?? '—',
                'due' => $loan->due_return_at?->format('M j') ?? '—',
                'status' => $loan->status,
            ])
            ->all();
    }

    /**
     * @param  array{summary: array<string, int>, faults: list<array<string, mixed>>}  $faultPayload
     * @param  array<string, array{count: int, items: list<array<string, mixed>>}>  $tasks
     * @return list<array<string, mixed>>
     */
    private function smartAlerts(array $faultPayload, int $weakSignals, array $tasks, int $tenantId): array
    {
        $alerts = [];
        $offlineCustomers = (int) Customer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('is_ppp_online', false)
            ->count();

        if ($tasks['escalated']['count'] > 0) {
            $alerts[] = [
                'tone' => 'rose',
                'label' => 'Escalated tickets',
                'value' => (string) $tasks['escalated']['count'],
                'hint' => 'High / critical priority',
            ];
        }

        if ($weakSignals > 0) {
            $alerts[] = [
                'tone' => 'amber',
                'label' => 'Weak signals',
                'value' => (string) $weakSignals,
                'hint' => 'Optical alerts open',
            ];
        }

        if ($offlineCustomers > 0) {
            $alerts[] = [
                'tone' => 'orange',
                'label' => 'Offline subscribers',
                'value' => number_format($offlineCustomers),
                'hint' => 'PPP down network-wide',
            ];
        }

        foreach (array_slice($faultPayload['faults'] ?? [], 0, 3) as $fault) {
            $alerts[] = [
                'tone' => ($fault['severity'] ?? '') === 'critical' ? 'rose' : 'amber',
                'label' => Str::limit((string) ($fault['title'] ?? 'Fault'), 28),
                'value' => (string) ($fault['affected'] ?? '—'),
                'hint' => 'Affected ONUs',
                'url' => $fault['url'] ?? FaultManagementHub::getUrl(),
            ];
        }

        if ($tasks['assigned']['count'] > 0) {
            $alerts[] = [
                'tone' => 'cyan',
                'label' => 'Pending visits',
                'value' => (string) count(array_filter($tasks['assigned']['items'], fn (array $i): bool => ($i['kind'] ?? '') === 'visit')),
                'hint' => 'Scheduled field work',
            ];
        }

        return array_slice($alerts, 0, 6);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTicket(SupportTicket $ticket): array
    {
        $customer = $ticket->customer;
        $lat = data_get($customer?->meta, 'gps_lat');
        $lng = data_get($customer?->meta, 'gps_lng');
        $mapsUrl = (is_numeric($lat) && is_numeric($lng))
            ? 'https://www.google.com/maps/search/?api=1&query='.$lat.','.$lng
            : (filled($customer?->address) ? 'https://www.google.com/maps/search/?api=1&query='.urlencode((string) $customer->address) : null);

        return [
            'id' => $ticket->id,
            'kind' => 'ticket',
            'number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'sla' => $ticket->sla_resolve_due_at?->format('M j, H:i') ?? '—',
            'sla_urgent' => $ticket->sla_resolve_due_at !== null && $ticket->sla_resolve_due_at->isPast(),
            'customer' => $customer?->name ?? '—',
            'code' => $customer?->customer_code ?? '—',
            'phone' => $customer?->phone,
            'address' => $customer?->address,
            'maps_url' => $mapsUrl,
            'resolved_today' => $ticket->resolved_at !== null && $ticket->resolved_at->isToday(),
            'url' => SupportTicketResource::getUrl('edit', ['record' => $ticket]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeVisit(FieldVisit $visit): array
    {
        $customer = $visit->ticket?->customer;
        $lat = $visit->latitude ?? data_get($customer?->meta, 'gps_lat');
        $lng = $visit->longitude ?? data_get($customer?->meta, 'gps_lng');
        $mapsUrl = (is_numeric($lat) && is_numeric($lng))
            ? 'https://www.google.com/maps/search/?api=1&query='.$lat.','.$lng
            : null;

        return [
            'id' => $visit->id,
            'kind' => 'visit',
            'status' => $visit->status,
            'scheduled' => $visit->scheduled_at?->format('M j, H:i') ?? '—',
            'is_today' => $visit->scheduled_at?->isToday() ?? false,
            'ticket_id' => $visit->support_ticket_id,
            'ticket' => '#'.($visit->support_ticket_id ?? '—'),
            'subject' => $visit->ticket?->subject ?? '—',
            'priority' => $visit->ticket?->priority ?? 'normal',
            'customer' => $customer?->name ?? '—',
            'code' => $customer?->customer_code ?? '—',
            'phone' => $customer?->phone,
            'address' => $customer?->address ?? $visit->location_text,
            'maps_url' => $mapsUrl,
            'latitude' => $lat,
            'longitude' => $lng,
            'url' => $visit->support_ticket_id
                ? SupportTicketResource::getUrl('edit', ['record' => $visit->support_ticket_id])
                : SupportTicketResource::getUrl(),
        ];
    }
}
