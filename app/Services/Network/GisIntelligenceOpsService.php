<?php

namespace App\Services\Network;

use App\Models\Customer;
use App\Models\FieldVisit;
use App\Models\FiberFaultLog;
use App\Models\FiberPlantEdge;
use App\Services\IspOs\NetworkDependencyTreeService;
use App\Support\CustomerStatus;
use App\Support\TenantResolver;
use Illuminate\Support\Collection;

/**
 * Read-only GIS intelligence layer (faults, heatmaps, technicians, timeline).
 */
final class GisIntelligenceOpsService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function build(array $payload): array
    {
        $tenantId = TenantResolver::requiredTenantId();
        $nodes = collect($payload['nodes'] ?? []);

        return [
            'config' => [
                'mapbox_token' => config('gis.mapbox_token'),
                'clustering' => config('gis.clustering', []),
                'pwa' => config('gis.pwa', []),
            ],
            'faults' => $this->buildFaults($tenantId, $nodes),
            'technicians' => $this->buildTechnicians($tenantId),
            'timeline' => $this->buildTimeline($tenantId, $nodes),
            'heatmaps' => $this->buildHeatmaps($nodes),
            'core_maps' => $this->buildCoreMaps($payload['edges'] ?? []),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function buildFaults(int $tenantId, Collection $nodes): array
    {
        $faults = [];

        FiberFaultLog::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('resolved_at')
            ->orderByDesc('detected_at')
            ->limit(50)
            ->with(['olt:id,display_name,serial_number'])
            ->get()
            ->each(function (FiberFaultLog $log) use (&$faults): void {
                $lat = data_get($log->meta, 'lat');
                $lng = data_get($log->meta, 'lng');

                $faults[] = [
                    'id' => 'fault-'.$log->id,
                    'type' => 'fiber_fault',
                    'severity' => $log->severity ?? 'high',
                    'title' => $log->fault_type ?: 'Fiber fault',
                    'description' => $log->description,
                    'affected_customers' => (int) ($log->affected_customer_count ?? 0),
                    'affected_onus' => (int) ($log->affected_onu_count ?? 0),
                    'detected_at' => $log->detected_at?->toIso8601String(),
                    'lat' => is_numeric($lat) ? (float) $lat : null,
                    'lng' => is_numeric($lng) ? (float) $lng : null,
                    'olt' => $log->olt?->adminLabel(),
                ];
            });

        $ponGroups = $nodes
            ->where('type', 'customer')
            ->filter(fn (array $n) => ($n['status'] ?? '') === 'onu_offline' && ! empty($n['ops']['pon']))
            ->groupBy(fn (array $n) => ($n['ops']['olt'] ?? 'unknown').'|'.($n['ops']['pon'] ?? ''));

        foreach ($ponGroups as $key => $group) {
            if ($group->count() < 3) {
                continue;
            }

            $withCoords = $group->filter(fn (array $n) => $n['lat'] !== null && $n['lng'] !== null);
            if ($withCoords->isEmpty()) {
                continue;
            }

            $lat = $withCoords->avg('lat');
            $lng = $withCoords->avg('lng');
            [$olt, $pon] = explode('|', (string) $key, 2);

            $faults[] = [
                'id' => 'pon-outage-'.md5($key),
                'type' => 'pon_outage',
                'severity' => $group->count() >= 10 ? 'critical' : 'high',
                'title' => 'PON outage cluster',
                'description' => $group->count().' ONU offline on '.$pon.' ('.$olt.')',
                'affected_customers' => $group->count(),
                'affected_onus' => $group->count(),
                'detected_at' => now()->toIso8601String(),
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'olt' => $olt !== 'unknown' ? $olt : null,
                'pon' => $pon ?: null,
            ];
        }

        return $faults;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildTechnicians(int $tenantId): array
    {
        return FieldVisit::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with([
                'assignee:id,name',
                'ticket:id,subject,customer_id',
                'ticket.customer:id,name,customer_code',
            ])
            ->orderByDesc('scheduled_at')
            ->limit(100)
            ->get()
            ->map(fn (FieldVisit $visit) => [
                'id' => (int) $visit->id,
                'name' => $visit->assignee?->name ?? 'Technician',
                'status' => $visit->status,
                'lat' => (float) $visit->latitude,
                'lng' => (float) $visit->longitude,
                'customer' => $visit->ticket?->customer?->name,
                'customer_code' => $visit->ticket?->customer?->customer_code,
                'scheduled_at' => $visit->scheduled_at?->toIso8601String(),
                'purpose' => $visit->ticket?->subject ?? $visit->location_text,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function buildTimeline(int $tenantId, Collection $nodes): array
    {
        $events = [];

        FiberFaultLog::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('detected_at')
            ->limit(30)
            ->get(['id', 'fault_type', 'severity', 'detected_at', 'resolved_at', 'affected_customer_count'])
            ->each(function (FiberFaultLog $log) use (&$events): void {
                $events[] = [
                    'at' => $log->detected_at?->toIso8601String(),
                    'type' => 'fault_detected',
                    'label' => $log->fault_type ?: 'Fiber fault',
                    'severity' => $log->severity ?? 'high',
                    'count' => (int) ($log->affected_customer_count ?? 0),
                ];
                if ($log->resolved_at) {
                    $events[] = [
                        'at' => $log->resolved_at->toIso8601String(),
                        'type' => 'fault_resolved',
                        'label' => 'Resolved: '.($log->fault_type ?: 'Fiber fault'),
                        'severity' => 'info',
                        'count' => 0,
                    ];
                }
            });

        $recentOffline = Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', CustomerStatus::TERMINATED)
            ->where('is_ppp_online', false)
            ->whereNotNull('ppp_last_seen_at')
            ->orderByDesc('ppp_last_seen_at')
            ->limit(20)
            ->get(['id', 'name', 'customer_code', 'ppp_last_seen_at']);

        foreach ($recentOffline as $customer) {
            $node = $nodes->first(fn (array $n) => (int) ($n['customer_id'] ?? 0) === (int) $customer->id);
            $events[] = [
                'at' => $customer->ppp_last_seen_at?->toIso8601String(),
                'type' => 'ppp_offline',
                'label' => $customer->name.' ('.$customer->customer_code.') offline',
                'severity' => 'medium',
                'customer_id' => (int) $customer->id,
                'lat' => $node['lat'] ?? null,
                'lng' => $node['lng'] ?? null,
            ];
        }

        usort($events, fn (array $a, array $b) => strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? '')));

        return array_slice($events, 0, 50);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $nodes
     * @return array{offline: list<array{0: float, 1: float, 2: float}>, weak_rx: list<array{0: float, 1: float, 2: float}>}
     */
    private function buildHeatmaps(Collection $nodes): array
    {
        $offline = [];
        $weakRx = [];

        foreach ($nodes->where('type', 'customer') as $node) {
            if ($node['lat'] === null || $node['lng'] === null) {
                continue;
            }

            $status = $node['status'] ?? 'unknown';
            $point = [(float) $node['lat'], (float) $node['lng'], 0.6];

            if (in_array($status, ['ppp_offline', 'onu_offline'], true)) {
                $point[2] = $status === 'onu_offline' ? 1.0 : 0.7;
                $offline[] = $point;
            }

            if (in_array($status, ['weak', 'critical'], true)) {
                $point[2] = $status === 'critical' ? 1.0 : 0.65;
                $weakRx[] = $point;
            }
        }

        return ['offline' => $offline, 'weak_rx' => $weakRx];
    }

    /**
     * @param  list<array<string, mixed>>  $edges
     * @return list<array<string, mixed>>
     */
    private function buildCoreMaps(array $edges): array
    {
        $edgeIds = collect($edges)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        if ($edgeIds === []) {
            return [];
        }

        return FiberPlantEdge::query()
            ->whereIn('id', $edgeIds)
            ->whereNotNull('core_map')
            ->get(['id', 'core_map', 'label', 'cable_type'])
            ->map(fn (FiberPlantEdge $edge) => [
                'edge_id' => (int) $edge->id,
                'label' => $edge->label,
                'cable_type' => $edge->cable_type,
                'core_map' => $edge->core_map,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{found: bool, chain: list<array<string, mixed>>}
     */
    public function dependencyTree(int $customerId): array
    {
        return app(NetworkDependencyTreeService::class)->forCustomer($customerId);
    }

    /**
     * @return array<string, mixed>
     */
    public function rcaForCustomer(int $customerId): array
    {
        $tree = $this->dependencyTree($customerId);
        $customer = Customer::query()->with('onuDevice')->find($customerId);

        if ($customer === null) {
            return ['found' => false, 'cards' => []];
        }

        $cards = [];
        $onu = $customer->onuDevice;
        $onuOper = strtolower((string) ($onu?->onu_oper_status ?? ''));

        if ($onu && ! in_array($onuOper, ['online', 'active', 'up'], true)) {
            $cards[] = [
                'title' => 'ONU offline / LOS',
                'detail' => $onu->offline_reason ?: 'Optical signal lost at customer premise',
                'severity' => 'critical',
                'action' => 'Check fiber drop, connector, and ONU power',
            ];
        }

        if (! $customer->is_ppp_online) {
            $cards[] = [
                'title' => 'PPP session down',
                'detail' => 'Last seen: '.($customer->ppp_last_seen_at?->diffForHumans() ?? 'unknown'),
                'severity' => 'high',
                'action' => 'Verify MikroTik secret, router power, and upstream link',
            ];
        }

        $rx = $onu?->rx_power_dbm;
        if ($rx !== null && (float) $rx < -27) {
            $cards[] = [
                'title' => 'Weak optical RX',
                'detail' => number_format((float) $rx, 1).' dBm — below acceptable threshold',
                'severity' => 'warning',
                'action' => 'Inspect splice, splitter port, and patch length',
            ];
        }

        if ($cards === []) {
            $cards[] = [
                'title' => 'No active fault detected',
                'detail' => 'Customer appears healthy on last poll',
                'severity' => 'info',
                'action' => null,
            ];
        }

        return [
            'found' => true,
            'customer_id' => $customerId,
            'chain' => $tree['chain'] ?? [],
            'cards' => $cards,
        ];
    }
}
