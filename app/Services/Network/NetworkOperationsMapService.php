<?php

namespace App\Services\Network;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\OltResource;
use App\Models\Customer;
use App\Models\Device;
use App\Support\CustomerStatus;
use App\Support\OnuSignalLevel;
use App\Support\SafeCache;
use App\Support\TenantResolver;
use Illuminate\Support\Collection;

/**
 * Live subscriber + ONU telemetry for the fiber GIS / network operations map.
 */
final class NetworkOperationsMapService
{
    private const ONLINE_ONU = ['online', 'active', 'up'];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function enrich(array $payload, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        return SafeCache::remember(
            'network_ops_map:'.$tenantId,
            now()->addSeconds(45),
            fn (): array => $this->buildEnriched($payload, $tenantId),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildEnriched(array $payload, int $tenantId): array
    {
        $nodes = collect($payload['nodes'] ?? []);
        $mappedCustomerIds = $nodes
            ->where('type', 'customer')
            ->pluck('customer_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $customers = $this->loadCustomers($tenantId, $mappedCustomerIds);
        $fiberPlant = app(FiberPlantMapService::class);
        $traces = [];
        foreach ($customers as $customer) {
            $traces[(int) $customer->id] = $fiberPlant->traceForCustomerId((int) $customer->id);
        }
        $opsByCustomerId = $customers->mapWithKeys(fn (Customer $c) => [
            (int) $c->id => $this->customerOps($c, $traces[(int) $c->id] ?? []),
        ]);

        $enrichedNodes = $nodes->map(function (array $node) use ($opsByCustomerId): array {
            if (($node['type'] ?? '') !== 'customer' || empty($node['customer_id'])) {
                return $node;
            }

            $ops = $opsByCustomerId->get((int) $node['customer_id']);
            if ($ops === null) {
                return $node;
            }

            $node['ops'] = $ops;
            $node['status'] = $ops['map_status'];
            $node['color'] = $ops['map_color'];

            return $node;
        })->values()->all();

        $extraMarkers = $this->unmappedGpsMarkers($customers, $mappedCustomerIds, $opsByCustomerId);

        $allCustomerMarkers = collect($enrichedNodes)
            ->where('type', 'customer')
            ->merge($extraMarkers);

        $olts = $this->oltFleetSummary($tenantId);
        $kpis = $this->computeKpis($allCustomerMarkers, $olts);

        $onMapByCustomerId = $allCustomerMarkers
            ->filter(fn (array $n) => ! empty($n['customer_id']))
            ->keyBy(fn (array $n) => (int) $n['customer_id']);

        $allNodes = array_merge(
            $this->attachOltCountsToNodes($enrichedNodes, $olts),
            $extraMarkers,
        );
        $allNodes = $this->attachSplitterStats($allNodes, $opsByCustomerId, $payload['edges'] ?? []);
        $payload['nodes'] = $allNodes;
        $payload['ops'] = [
            'kpis' => $kpis,
            'search_index' => $this->buildSearchIndex($tenantId, $onMapByCustomerId, $opsByCustomerId),
            'drop_lines' => $this->buildDropLines(collect($allNodes), $payload['edges'] ?? []),
            'fiber_paths' => $this->buildFiberPaths(collect($allNodes), $opsByCustomerId),
            'olts' => $olts,
            'coordinate_problems' => $this->coordinateProblems($tenantId, $customers, collect($allNodes)),
            'refreshed_at' => now()->toIso8601String(),
            'status_legend' => [
                ['key' => 'online', 'label' => 'PPP online · ONU up', 'color' => '#16a34a'],
                ['key' => 'ppp_offline', 'label' => 'PPP offline', 'color' => '#dc2626'],
                ['key' => 'onu_offline', 'label' => 'ONU down / LOS', 'color' => '#b91c1c'],
                ['key' => 'weak', 'label' => 'Weak RX (fiber meter)', 'color' => '#ca8a04'],
                ['key' => 'critical', 'label' => 'Critical RX', 'color' => '#ea580c'],
                ['key' => 'unknown', 'label' => 'No ONU / unknown', 'color' => '#64748b'],
            ],
        ];

        return $payload;
    }

    /**
     * @param  list<int>  $mappedCustomerIds
     * @return Collection<int, Customer>
     */
    private function loadCustomers(int $tenantId, array $mappedCustomerIds): Collection
    {
        return Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', CustomerStatus::TERMINATED)
            ->where(function ($q) use ($mappedCustomerIds): void {
                if ($mappedCustomerIds !== []) {
                    $q->whereIn('id', $mappedCustomerIds);
                }
                $q->orWhereRaw("meta->>'gps_lat' IS NOT NULL AND meta->>'gps_lng' IS NOT NULL");
            })
            ->with([
                'zone:id,name',
                'area:id,name',
                'subzone:id,name',
                'mikrotikServer:id,name',
                'package:id,name',
                'onuDevice' => fn ($q) => $q->select([
                    'devices.id', 'devices.customer_id', 'devices.olt_id', 'devices.olt_port_id', 'devices.type',
                    'devices.serial_number', 'devices.mac_address', 'devices.display_name',
                    'devices.rx_power_dbm', 'devices.tx_power_dbm', 'devices.onu_oper_status', 'devices.offline_reason',
                    'devices.card_no', 'devices.pon_no', 'devices.last_polled_at',
                ])->with([
                    'olt:id,display_name,serial_number',
                    'oltPort:id,label,card_index,pon_index',
                ]),
                'lastEndedPppSession' => fn ($q) => $q->select([
                    'ppp_session_logs.id',
                    'ppp_session_logs.customer_id',
                    'ppp_session_logs.ended_at',
                ]),
            ])
            ->withExists([
                'invoices as has_due_invoice' => fn ($q) => $q
                    ->whereIn('status', \App\Support\CustomerBalanceDue::OPEN_INVOICE_STATUSES)
                    ->whereRaw('(total - amount_paid) > 0.009'),
            ])
            ->get([
                'id', 'tenant_id', 'name', 'phone', 'customer_code', 'status',
                'radius_username', 'mikrotik_secret_name', 'mikrotik_server_id',
                'area_id', 'zone_id', 'subzone_id', 'address', 'meta', 'package_id',
                'account_balance', 'is_ppp_online', 'ppp_last_seen_at', 'service_expires_at',
            ]);
    }

    /**
     * @param  array<string, mixed>  $fiberTrace
     * @return array<string, mixed>
     */
    public function customerOps(Customer $customer, array $fiberTrace = []): array
    {
        $onu = $customer->onuDevice;
        $oper = strtolower((string) ($onu?->onu_oper_status ?? ''));
        $onuOnline = $onu !== null && in_array($oper, self::ONLINE_ONU, true);
        $rx = $onu?->rx_power_dbm !== null ? (float) $onu->rx_power_dbm : null;
        $signalLevel = OnuSignalLevel::classifyRx($rx, $oper);
        $pppOnline = $customer->isPppOnline();
        $offlineReason = $pppOnline ? null : $this->pppOfflineReason($customer);

        $mapStatus = $this->resolveMapStatus($pppOnline, $onu, $onuOnline, $signalLevel);
        $mapColor = $this->statusColor($mapStatus);

        $oltLabel = $onu?->olt?->display_name;
        $ponLabel = $onu?->oltPort?->label;
        if ($ponLabel === null && ($onu?->card_no !== null || $onu?->pon_no !== null)) {
            $ponLabel = trim(($onu->card_no ?? '0').'/'.($onu->pon_no ?? '0'), '/');
        }

        $lastLogout = $customer->lastEndedPppSession?->ended_at ?? $customer->ppp_last_seen_at;
        $fiberM = $fiberTrace['total_length_m'] ?? data_get($customer->meta, 'cable_length_m');
        $fiberM = is_numeric($fiberM) ? (float) $fiberM : null;
        $upstream = $this->upstreamLabel($fiberTrace, (int) $customer->id);

        return [
            'ppp_online' => $pppOnline,
            'ppp_login' => $customer->pppLoginName(),
            'radius_username' => $customer->radius_username,
            'ppp_last_seen' => $customer->ppp_last_seen_at?->diffForHumans(),
            'ppp_last_seen_at' => $customer->ppp_last_seen_at?->format('d M Y, h:i A'),
            'last_logout_at' => $lastLogout?->format('d M Y, h:i A'),
            'last_logout_ago' => $lastLogout?->diffForHumans(),
            'ppp_offline_reason' => $offlineReason,
            'onu_online' => $onu === null ? null : $onuOnline,
            'onu_oper_status' => $onu?->onu_oper_status,
            'onu_offline_reason' => $onu?->offline_reason,
            'onu_serial' => $onu?->serial_number ?: $onu?->mac_address,
            'onu_last_polled' => $onu?->last_polled_at?->diffForHumans(),
            'rx_dbm' => $rx,
            'tx_dbm' => $onu?->tx_power_dbm !== null ? (float) $onu->tx_power_dbm : null,
            'signal_level' => $signalLevel,
            'signal_label' => OnuSignalLevel::labels()[$signalLevel] ?? 'Unknown',
            'olt' => $oltLabel,
            'olt_id' => $onu?->olt_id,
            'olt_url' => $onu?->olt_id ? OltResource::getUrl('edit', ['record' => $onu->olt_id]) : null,
            'pon' => $ponLabel,
            'mikrotik' => $customer->mikrotikServer?->name,
            'zone' => $customer->zone?->name,
            'area' => $customer->area?->name,
            'subzone' => $customer->subzone?->name,
            'package' => $customer->package?->name,
            'account_balance' => $customer->account_balance !== null ? (float) $customer->account_balance : null,
            'has_due' => (bool) ($customer->has_due_invoice ?? false),
            'customer_status' => $customer->status,
            'upstream' => $upstream,
            'fiber_distance_m' => $fiberM,
            'fiber_segments' => $fiberTrace['segments'] ?? [],
            'map_status' => $mapStatus,
            'map_color' => $mapColor,
            'customer_url' => CustomerResource::getUrl('view', ['record' => $customer->id]),
            'fiber_meter' => $this->fiberMeterState($rx, $signalLevel, $onuOnline),
        ];
    }

    /**
     * @param  list<int>  $mappedCustomerIds
     * @param  Collection<int, array<string, mixed>>  $opsByCustomerId
     * @return list<array<string, mixed>>
     */
    private function unmappedGpsMarkers(Collection $customers, array $mappedCustomerIds, Collection $opsByCustomerId): array
    {
        $mapped = array_flip($mappedCustomerIds);
        $markers = [];

        foreach ($customers as $customer) {
            if (isset($mapped[(int) $customer->id])) {
                continue;
            }

            $lat = data_get($customer->meta, 'gps_lat');
            $lng = data_get($customer->meta, 'gps_lng');
            if (! is_numeric($lat) || ! is_numeric($lng)) {
                continue;
            }

            $trace = app(FiberPlantMapService::class)->traceForCustomerId((int) $customer->id);
            $ops = $opsByCustomerId->get((int) $customer->id)
                ?? $this->customerOps($customer, $trace);
            $phone = trim((string) ($customer->phone ?? ''));

            $markers[] = [
                'id' => 'gps-'.$customer->id,
                'code' => 'SUB-'.$customer->id,
                'name' => trim($customer->name.($phone !== '' ? ' · '.$phone : '')),
                'type' => 'customer',
                'type_label' => 'Customer / ONU',
                'color' => $ops['map_color'],
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'address' => $customer->address,
                'customer_id' => $customer->id,
                'phone' => $customer->phone,
                'customer_code' => $customer->customer_code,
                'unmapped' => true,
                'ops' => $ops,
                'status' => $ops['map_status'],
            ];
        }

        return $markers;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $markers
     * @param  list<array<string, mixed>>  $olts
     * @return array<string, int>
     */
    private function computeKpis(Collection $markers, array $olts): array
    {
        $total = $markers->count();
        $pppOnline = $markers->filter(fn (array $n) => (bool) ($n['ops']['ppp_online'] ?? false))->count();
        $onuOffline = $markers->filter(fn (array $n) => ($n['ops']['onu_online'] ?? null) === false)->count();
        $onuOnlineOnMap = $markers->filter(fn (array $n) => ($n['ops']['onu_online'] ?? null) === true)->count();
        $weak = $markers->filter(fn (array $n) => in_array($n['status'] ?? '', ['weak', 'critical'], true))->count();
        $pppOffline = $total - $pppOnline;
        $onuTotalFleet = (int) collect($olts)->sum('onu_total');
        $onuOnlineFleet = (int) collect($olts)->sum('onu_online');

        return [
            'subscribers_on_map' => $total,
            'ppp_online' => $pppOnline,
            'ppp_offline' => $pppOffline,
            'onu_offline' => $onuOffline,
            'onu_online_map' => $onuOnlineOnMap,
            'onu_online_fleet' => $onuOnlineFleet,
            'onu_total_fleet' => $onuTotalFleet,
            'weak_signal' => $weak,
            'unmapped_gps' => $markers->filter(fn (array $n) => (bool) ($n['unmapped'] ?? false))->count(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $olts
     * @return list<array<string, mixed>>
     */
    private function attachOltCountsToNodes(array $nodes, array $olts): array
    {
        $oltById = collect($olts)->keyBy('id');

        return collect($nodes)->map(function (array $node) use ($oltById): array {
            if (($node['type'] ?? '') !== 'olt' || empty($node['device_id'])) {
                return $node;
            }

            $olt = $oltById->get((int) $node['device_id']);
            if ($olt === null) {
                return $node;
            }

            $node['onu_online'] = (int) ($olt['onu_online'] ?? 0);
            $node['onu_total'] = (int) ($olt['onu_total'] ?? 0);

            return $node;
        })->values()->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $onMapByCustomerId
     * @param  Collection<int, array<string, mixed>>  $opsByCustomerId
     * @return list<array<string, mixed>>
     */
    private function buildSearchIndex(int $tenantId, Collection $onMapByCustomerId, Collection $opsByCustomerId): array
    {
        return Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', CustomerStatus::TERMINATED)
            ->with(['onuDevice' => fn ($q) => $q->select([
                'devices.id', 'devices.customer_id', 'devices.onu_oper_status', 'devices.rx_power_dbm',
            ])])
            ->orderBy('name')
            ->limit(1000)
            ->get(['id', 'name', 'phone', 'customer_code', 'radius_username', 'mikrotik_secret_name', 'meta'])
            ->map(function (Customer $customer) use ($onMapByCustomerId, $opsByCustomerId): array {
                $onMap = $onMapByCustomerId->get((int) $customer->id);
                $ops = $opsByCustomerId->get((int) $customer->id);
                $lat = data_get($customer->meta, 'gps_lat');
                $lng = data_get($customer->meta, 'gps_lng');
                $hasGps = is_numeric($lat) && is_numeric($lng);
                $onu = $customer->onuDevice;
                $onuOper = strtolower((string) ($onu?->onu_oper_status ?? ''));
                $onuOnline = $onu !== null && in_array($onuOper, self::ONLINE_ONU, true);

                return [
                    'id' => (int) $customer->id,
                    'node_id' => $onMap['id'] ?? ($hasGps ? 'gps-'.$customer->id : null),
                    'label' => $customer->name,
                    'login' => $customer->pppLoginName(),
                    'code' => $customer->customer_code,
                    'phone' => $customer->phone,
                    'on_map' => $onMap !== null || $hasGps,
                    'has_gps' => $hasGps,
                    'lat' => $hasGps ? (float) $lat : null,
                    'lng' => $hasGps ? (float) $lng : null,
                    'status' => $onMap['status'] ?? ($ops['map_status'] ?? 'unknown'),
                    'onu_online' => $onu === null ? null : $onuOnline,
                    'onu_oper_status' => $onu?->onu_oper_status,
                    'rx_dbm' => $ops['rx_dbm'] ?? ($onu?->rx_power_dbm !== null ? (float) $onu->rx_power_dbm : null),
                    'pon' => $ops['pon'] ?? null,
                    'olt' => $ops['olt'] ?? null,
                    'edit_url' => CustomerResource::getUrl('edit', ['record' => $customer->id]).'?tab=location-staff',
                    'view_url' => CustomerResource::getUrl('view', ['record' => $customer->id]),
                ];
            })
            ->values()
            ->all();
    }

    private function resolveMapStatus(bool $pppOnline, ?\App\Models\Device $onu, bool $onuOnline, string $signalLevel): string
    {
        if ($onu !== null && ! $onuOnline) {
            return 'onu_offline';
        }

        if (! $pppOnline) {
            return 'ppp_offline';
        }

        if (in_array($signalLevel, [OnuSignalLevel::CRITICAL, OnuSignalLevel::HIGH], true)) {
            return 'critical';
        }

        if ($signalLevel === OnuSignalLevel::WARNING) {
            return 'weak';
        }

        if ($pppOnline && ($onu === null || $onuOnline)) {
            return 'online';
        }

        return 'unknown';
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'online' => '#16a34a',
            'ppp_offline' => '#dc2626',
            'onu_offline' => '#b91c1c',
            'weak' => '#ca8a04',
            'critical' => '#ea580c',
            default => '#64748b',
        };
    }

    /**
     * @return array{value: string, color: string, label: string}
     */
    private function fiberMeterState(?float $rx, string $signalLevel, bool $onuOnline): array
    {
        if (! $onuOnline) {
            return ['value' => '—', 'color' => '#dc2626', 'label' => 'OFF'];
        }

        $value = $rx !== null ? number_format($rx, 1).' dBm' : '—';
        $color = match ($signalLevel) {
            OnuSignalLevel::EXCELLENT, OnuSignalLevel::GOOD => '#16a34a',
            OnuSignalLevel::WARNING => '#ca8a04',
            OnuSignalLevel::CRITICAL, OnuSignalLevel::HIGH => '#ea580c',
            default => '#64748b',
        };

        return ['value' => $value, 'color' => $color, 'label' => strtoupper($signalLevel)];
    }

    private function pppOfflineReason(Customer $customer): string
    {
        $lastSeen = $customer->ppp_last_seen_at ?? $customer->lastEndedPppSession?->ended_at;

        return match (true) {
            $customer->status === CustomerStatus::SUSPENDED => 'Suspended',
            $customer->isServiceExpired() => 'Expired / billing due',
            (bool) ($customer->has_due_invoice ?? false) => 'Due balance',
            $lastSeen !== null && $lastSeen->greaterThan(now()->subMinutes(30)) => 'Recently disconnected',
            $lastSeen === null => 'Never came online',
            default => 'Offline / auth issue',
        };
    }

    /**
     * @param  array<string, mixed>  $fiberTrace
     */
    private function upstreamLabel(array $fiberTrace, int $customerId): ?string
    {
        $nodes = $fiberTrace['nodes'] ?? [];
        if ($nodes === []) {
            return null;
        }

        $parent = null;
        $count = count($nodes);
        for ($i = $count - 1; $i >= 0; $i--) {
            $node = $nodes[$i];
            if (! is_array($node)) {
                continue;
            }
            if ((int) ($node['customer_id'] ?? 0) === $customerId) {
                $parent = $nodes[$i - 1] ?? null;
                break;
            }
        }

        if (! is_array($parent)) {
            $segments = $fiberTrace['segments'] ?? [];
            $firstSeg = $segments[0] ?? null;

            return is_array($firstSeg) ? (string) ($firstSeg['from'] ?? null) : null;
        }

        return ($parent['type_label'] ?? $parent['type'] ?? 'Node').': '.($parent['name'] ?? '—');
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  Collection<int, array<string, mixed>>  $opsByCustomerId
     * @return list<array<string, mixed>>
     */
    private function buildFiberPaths(Collection $nodes, Collection $opsByCustomerId): array
    {
        $paths = [];

        foreach ($nodes as $node) {
            if (($node['type'] ?? '') !== 'customer' || empty($node['customer_id']) || $node['lat'] === null) {
                continue;
            }

            $ops = $node['ops'] ?? $opsByCustomerId->get((int) $node['customer_id']);
            $segments = $ops['fiber_segments'] ?? [];
            if ($segments === []) {
                continue;
            }

            $points = [];
            $pathSegments = [];

            foreach ($segments as $seg) {
                if (! is_array($seg)) {
                    continue;
                }

                $fromLat = $seg['from_lat'] ?? null;
                $fromLng = $seg['from_lng'] ?? null;
                $toLat = $seg['to_lat'] ?? null;
                $toLng = $seg['to_lng'] ?? null;

                if (! is_numeric($fromLat) || ! is_numeric($fromLng) || ! is_numeric($toLat) || ! is_numeric($toLng)) {
                    continue;
                }

                $from = [(float) $fromLat, (float) $fromLng];
                $to = [(float) $toLat, (float) $toLng];

                if ($points === [] || $points[count($points) - 1][0] !== $from[0] || $points[count($points) - 1][1] !== $from[1]) {
                    $points[] = $from;
                }
                $points[] = $to;

                $pathSegments[] = [
                    'from' => $seg['from'] ?? '—',
                    'to' => $seg['to'] ?? '—',
                    'length_m' => $seg['length_m'] ?? null,
                    'direction' => $seg['direction_display'] ?? $seg['direction'] ?? null,
                    'cable_type' => $seg['cable_type'] ?? null,
                    'color' => $seg['cable_color_hex'] ?? '#2563eb',
                    'pon' => $seg['pon_label'] ?? ($ops['pon'] ?? null),
                ];
            }

            if (count($points) < 2) {
                continue;
            }

            $paths[] = [
                'customer_id' => (int) $node['customer_id'],
                'points' => $points,
                'segments' => $pathSegments,
                'total_m' => $ops['fiber_distance_m'] ?? null,
                'pon' => $ops['pon'] ?? null,
                'olt' => $ops['olt'] ?? null,
                'status' => $node['status'] ?? 'unknown',
                'color' => $node['color'] ?? '#2563eb',
            ];
        }

        return $paths;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  Collection<int, array<string, mixed>>  $opsByCustomerId
     * @param  list<array<string, mixed>>  $edges
     * @return list<array<string, mixed>>
     */
    private function attachSplitterStats(array $nodes, Collection $opsByCustomerId, array $edges): array
    {
        $downstreamBySplitter = [];

        foreach ($edges as $edge) {
            $toId = (int) ($edge['to_node_id'] ?? 0);
            $fromId = (int) ($edge['from_node_id'] ?? 0);
            if ($fromId > 0 && $toId > 0) {
                $downstreamBySplitter[$fromId][] = $toId;
            }
        }

        $nodeById = collect($nodes)->keyBy('id');

        return collect($nodes)->map(function (array $node) use ($downstreamBySplitter, $nodeById, $opsByCustomerId): array {
            if (($node['type'] ?? '') !== 'splitter') {
                return $node;
            }

            $childIds = $downstreamBySplitter[(int) ($node['id'] ?? 0)] ?? [];
            $customerCount = 0;
            $ponVotes = [];

            foreach ($childIds as $childId) {
                $child = $nodeById->get($childId);
                if (! is_array($child)) {
                    continue;
                }

                if (($child['type'] ?? '') === 'customer') {
                    $customerCount++;
                    $pon = $child['ops']['pon'] ?? null;
                    if ($pon) {
                        $ponVotes[(string) $pon] = ($ponVotes[(string) $pon] ?? 0) + 1;
                    }
                }
            }

            arsort($ponVotes);
            $autoPon = array_key_first($ponVotes);

            if (empty($node['pon_label']) && $autoPon) {
                $node['pon_label'] = $autoPon;
                $node['pon_source'] = 'auto';
            } elseif (! empty($node['pon_label'])) {
                $node['pon_source'] = 'manual';
            }

            $node['downstream_customers'] = $customerCount;

            return $node;
        })->values()->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $nodes
     * @param  list<array<string, mixed>>  $edges
     * @return list<array<string, mixed>>
     */
    private function buildDropLines(Collection $nodes, array $edges): array
    {
        $lines = [];
        $infra = $nodes->filter(fn (array $n) => in_array($n['type'] ?? '', ['splitter', 'pop', 'olt', 'closure', 'junction'], true)
            && $n['lat'] !== null && $n['lng'] !== null);

        foreach ($nodes as $node) {
            if (($node['type'] ?? '') !== 'customer' || $node['lat'] === null) {
                continue;
            }

            $status = $node['status'] ?? 'unknown';
            $color = $node['color'] ?? '#2563eb';
            $lengthM = $node['ops']['fiber_distance_m'] ?? null;

            $matchedEdge = collect($edges)->first(function (array $edge) use ($node): bool {
                return (int) ($edge['to_node_id'] ?? 0) === (int) ($node['id'] ?? -1)
                    || (isset($node['customer_id']) && str_contains((string) ($edge['label'] ?? ''), (string) $node['customer_id']));
            });

            if ($matchedEdge && $matchedEdge['from'] && $matchedEdge['to']) {
                $lines[] = [
                    'from' => $matchedEdge['from'],
                    'to' => $matchedEdge['to'],
                    'color' => $matchedEdge['cable_color_hex'] ?? $color,
                    'status' => $status,
                    'length_m' => $matchedEdge['length_m'] ?? $lengthM,
                    'customer_id' => $node['customer_id'] ?? null,
                    'from_name' => $matchedEdge['from_name'] ?? null,
                    'to_name' => $matchedEdge['to_name'] ?? $node['name'] ?? null,
                    'direction' => $matchedEdge['direction_display'] ?? $matchedEdge['direction_label'] ?? null,
                    'pon' => $node['ops']['pon'] ?? null,
                    'olt' => $node['ops']['olt'] ?? null,
                    'cable_type' => $matchedEdge['cable_type_label'] ?? null,
                    'source' => ($matchedEdge['auto_linked'] ?? false) ? 'auto' : 'manual',
                    'dashed' => ($matchedEdge['cable_type'] ?? '') === 'drop',
                ];

                continue;
            }

            $parent = $this->nearestInfraNode($infra, (float) $node['lat'], (float) $node['lng']);
            if ($parent === null) {
                continue;
            }

            $dist = $lengthM ?? $this->haversineM(
                (float) $parent['lat'],
                (float) $parent['lng'],
                (float) $node['lat'],
                (float) $node['lng'],
            );

            $lines[] = [
                'from' => [(float) $parent['lat'], (float) $parent['lng']],
                'to' => [(float) $node['lat'], (float) $node['lng']],
                'color' => $color,
                'status' => $status,
                'length_m' => round($dist, 1),
                'customer_id' => $node['customer_id'] ?? null,
                'from_name' => $parent['name'] ?? null,
                'to_name' => $node['name'] ?? null,
                'direction' => data_get($node, 'ops.fiber_segments.0.direction_display')
                    ?? data_get($node, 'ops.fiber_segments.0.direction'),
                'pon' => $node['ops']['pon'] ?? $parent['pon_label'] ?? null,
                'olt' => $node['ops']['olt'] ?? $parent['olt_label'] ?? null,
                'cable_type' => 'Drop (estimated)',
                'source' => 'auto',
                'dashed' => true,
                'virtual' => true,
            ];
        }

        return $lines;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $infra
     * @return array<string, mixed>|null
     */
    private function nearestInfraNode(Collection $infra, float $lat, float $lng): ?array
    {
        $best = null;
        $bestDist = PHP_FLOAT_MAX;

        foreach ($infra as $node) {
            $d = $this->haversineM($lat, $lng, (float) $node['lat'], (float) $node['lng']);
            if ($d < $bestDist) {
                $bestDist = $d;
                $best = $node;
            }
        }

        return $best;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function oltFleetSummary(int $tenantId): array
    {
        return Device::query()
            ->where('tenant_id', $tenantId)
            ->olts()
            ->where('status', '!=', 'decommissioned')
            ->withCount([
                'onus as onu_total',
                'onus as onu_online' => fn ($q) => $q->whereIn('onu_oper_status', self::ONLINE_ONU),
            ])
            ->orderBy('display_name')
            ->limit(12)
            ->get(['id', 'display_name', 'management_ip', 'status'])
            ->map(fn (Device $olt): array => [
                'id' => $olt->id,
                'label' => $olt->adminLabel(),
                'ip' => $olt->management_ip,
                'status' => $olt->status,
                'onu_total' => (int) ($olt->onu_total ?? 0),
                'onu_online' => (int) ($olt->onu_online ?? 0),
                'url' => OltResource::getUrl('edit', ['record' => $olt->id]),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Customer>  $customersOnMap
     * @param  Collection<int, array<string, mixed>>  $nodes
     * @return array{missing_gps: list<array<string, mixed>>, missing_coords: list<array<string, mixed>>}
     */
    private function coordinateProblems(int $tenantId, Collection $customersOnMap, Collection $nodes): array
    {
        $withGps = $customersOnMap->filter(fn (Customer $c) => is_numeric(data_get($c->meta, 'gps_lat'))
            && is_numeric(data_get($c->meta, 'gps_lng')))->pluck('id')->all();

        $missingGps = Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('status', CustomerStatus::ACTIVE)
            ->whereNotIn('id', $withGps)
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'customer_code', 'phone', 'address'])
            ->map(fn (Customer $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->customer_code,
                'url' => CustomerResource::getUrl('edit', ['record' => $c->id]).'?tab=location-staff',
            ])
            ->values()
            ->all();

        $missingCoords = $nodes
            ->filter(fn (array $n) => in_array($n['type'] ?? '', ['olt', 'splitter', 'pop'], true)
                && ($n['lat'] === null || $n['lng'] === null))
            ->map(fn (array $n): array => [
                'id' => $n['id'],
                'name' => $n['name'] ?? 'Node',
                'type' => $n['type_label'] ?? $n['type'],
            ])
            ->values()
            ->all();

        return [
            'missing_gps' => $missingGps,
            'missing_coords' => $missingCoords,
        ];
    }

    private function haversineM(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
