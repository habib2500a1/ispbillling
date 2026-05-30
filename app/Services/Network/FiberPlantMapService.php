<?php

namespace App\Services\Network;

use App\Models\Customer;
use App\Models\Device;
use App\Models\FiberPlantEdge;
use App\Models\FiberPlantNode;
use App\Models\PopBox;
use App\Support\TenantResolver;
use Illuminate\Support\Collection;

final class FiberPlantMapService
{
    /**
     * @return array{
     *     nodes: list<array<string, mixed>>,
     *     edges: list<array<string, mixed>>,
     *     stats: array<string, int|float>,
     *     config: array<string, mixed>,
     *     center: array{lat: float, lng: float, zoom: int}
     * }
     */
    public function buildPayload(?int $highlightCustomerId = null): array
    {
        $nodes = FiberPlantNode::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $edges = FiberPlantEdge::query()
            ->where('is_active', true)
            ->with(['fromNode:id,latitude,longitude,name', 'toNode:id,latitude,longitude,name'])
            ->get();

        $highlightPath = $highlightCustomerId
            ? collect($this->traceForCustomerId($highlightCustomerId)['segments'] ?? [])->pluck('edge_id')->filter()->all()
            : [];

        $nodePayload = $nodes->map(fn (FiberPlantNode $node) => $this->serializeNode($node))->values()->all();
        $edgePayload = $edges->map(function (FiberPlantEdge $edge) use ($highlightPath) {
            $data = $this->serializeEdge($edge);
            $data['highlighted'] = in_array($edge->id, $highlightPath, true);

            return $data;
        })->values()->all();

        return [
            'nodes' => $nodePayload,
            'edges' => $edgePayload,
            'stats' => $this->stats($nodes, $edges),
            'config' => [
                'cable_colors' => config('fiber_plant.cable_colors', []),
                'cable_types' => config('fiber_plant.cable_types', []),
                'node_types' => config('fiber_plant.node_types', []),
                'directions' => config('fiber_plant.directions', []),
                'splitter_ratios' => config('fiber_plant.splitter_ratios', []),
            ],
            'center' => $this->resolveCenter($nodes),
        ];
    }

    /**
     * @return array{
     *     found: bool,
     *     total_length_m: float,
     *     segments: list<array<string, mixed>>,
     *     nodes: list<array<string, mixed>>
     * }
     */
    public function traceForCustomerId(int $customerId): array
    {
        $node = FiberPlantNode::query()
            ->where('customer_id', $customerId)
            ->where('is_active', true)
            ->first();

        if ($node === null) {
            $customer = Customer::query()->find($customerId);
            if ($customer === null) {
                return ['found' => false, 'total_length_m' => 0, 'segments' => [], 'nodes' => []];
            }

            return $this->traceFromCustomerMeta($customer);
        }

        return $this->traceFromNode($node);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertNode(?int $id, array $data): FiberPlantNode
    {
        $node = $id
            ? FiberPlantNode::query()->findOrFail($id)
            : new FiberPlantNode;

        if (! $id && empty($data['code'])) {
            $data['code'] = $this->suggestCode((string) ($data['type'] ?? 'other'));
        }

        $node->fill([
            'code' => $data['code'] ?? $node->code,
            'name' => $data['name'] ?? $node->name ?? 'Node',
            'type' => $data['type'] ?? $node->type ?? 'other',
            'latitude' => $data['latitude'] ?? $node->latitude,
            'longitude' => $data['longitude'] ?? $node->longitude,
            'address' => $data['address'] ?? $node->address,
            'pop_box_id' => $data['pop_box_id'] ?? $node->pop_box_id,
            'device_id' => $data['device_id'] ?? $node->device_id,
            'customer_id' => $data['customer_id'] ?? $node->customer_id,
            'splitter_ratio' => $data['splitter_ratio'] ?? $node->splitter_ratio,
            'splitter_direction' => $data['splitter_direction'] ?? $node->splitter_direction,
            'bearing_deg' => $data['bearing_deg'] ?? $node->bearing_deg,
            'notes' => $data['notes'] ?? $node->notes,
            'is_active' => $data['is_active'] ?? $node->is_active ?? true,
        ]);

        $node->save();

        return $node->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertEdge(?int $id, array $data): FiberPlantEdge
    {
        $edge = $id
            ? FiberPlantEdge::query()->findOrFail($id)
            : new FiberPlantEdge;

        $fromId = (int) ($data['from_node_id'] ?? $edge->from_node_id);
        $toId = (int) ($data['to_node_id'] ?? $edge->to_node_id);

        if ($fromId === $toId) {
            throw new \InvalidArgumentException('Cable cannot connect a node to itself.');
        }

        $from = FiberPlantNode::query()->findOrFail($fromId);
        $to = FiberPlantNode::query()->findOrFail($toId);

        $lengthM = isset($data['length_m']) && (float) $data['length_m'] > 0
            ? (float) $data['length_m']
            : $this->estimateLengthM($from, $to);

        $edge->fill([
            'from_node_id' => $fromId,
            'to_node_id' => $toId,
            'cable_type' => $data['cable_type'] ?? $edge->cable_type ?? 'distribution',
            'fiber_count' => (int) ($data['fiber_count'] ?? $edge->fiber_count ?? 2),
            'cable_color' => $data['cable_color'] ?? $edge->cable_color ?? 'blue',
            'tube_color' => $data['tube_color'] ?? $edge->tube_color,
            'length_m' => $lengthM,
            'direction_label' => $data['direction_label'] ?? $edge->direction_label,
            'bearing_deg' => $data['bearing_deg'] ?? $edge->bearing_deg,
            'notes' => $data['notes'] ?? $edge->notes,
            'is_active' => $data['is_active'] ?? $edge->is_active ?? true,
        ]);

        $edge->save();

        return $edge->fresh(['fromNode', 'toNode']);
    }

    public function deleteNode(int $id): void
    {
        FiberPlantEdge::query()
            ->where('from_node_id', $id)
            ->orWhere('to_node_id', $id)
            ->delete();

        FiberPlantNode::query()->whereKey($id)->delete();
    }

    public function deleteEdge(int $id): void
    {
        FiberPlantEdge::query()->whereKey($id)->delete();
    }

    public function importPopBoxes(): int
    {
        $count = 0;

        PopBox::query()
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->each(function (PopBox $pop) use (&$count): void {
                $exists = FiberPlantNode::query()->where('pop_box_id', $pop->id)->exists();
                if ($exists) {
                    return;
                }

                FiberPlantNode::query()->create([
                    'code' => 'POP-'.$pop->code,
                    'name' => $pop->name,
                    'type' => 'pop',
                    'latitude' => $pop->latitude,
                    'longitude' => $pop->longitude,
                    'address' => $pop->address,
                    'pop_box_id' => $pop->id,
                    'notes' => $pop->notes,
                ]);

                $count++;
            });

        return $count;
    }

    public function importOlts(): int
    {
        $count = 0;

        Device::query()
            ->olts()
            ->each(function (Device $olt) use (&$count): void {
                if (FiberPlantNode::query()->where('device_id', $olt->id)->exists()) {
                    return;
                }

                $lat = data_get($olt->meta, 'latitude');
                $lng = data_get($olt->meta, 'longitude');

                FiberPlantNode::query()->create([
                    'code' => 'OLT-'.($olt->serial_number ?: $olt->id),
                    'name' => $olt->display_name ?: $olt->name ?: ('OLT #'.$olt->id),
                    'type' => 'olt',
                    'latitude' => is_numeric($lat) ? $lat : null,
                    'longitude' => is_numeric($lng) ? $lng : null,
                    'address' => $olt->location,
                    'device_id' => $olt->id,
                ]);

                $count++;
            });

        return $count;
    }

    public function importCustomerNodes(): int
    {
        $count = 0;

        Customer::query()
            ->whereNotNull('meta')
            ->chunk(200, function ($customers) use (&$count): void {
                foreach ($customers as $customer) {
                    if (FiberPlantNode::query()->where('customer_id', $customer->id)->exists()) {
                        continue;
                    }

                    $lat = data_get($customer->meta, 'gps_lat');
                    $lng = data_get($customer->meta, 'gps_lng');

                    if (! is_numeric($lat) || ! is_numeric($lng)) {
                        continue;
                    }

                    FiberPlantNode::query()->create([
                        'code' => 'SUB-'.$customer->id,
                        'name' => $customer->name,
                        'type' => 'customer',
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'address' => $customer->address,
                        'customer_id' => $customer->id,
                    ]);

                    $count++;
                }
            });

        return $count;
    }

    public function suggestCode(string $type): string
    {
        $prefix = strtoupper(match ($type) {
            'olt' => 'OLT',
            'pop' => 'POP',
            'splitter' => 'SPL',
            'pole' => 'POL',
            'junction' => 'JNC',
            'closure' => 'CLS',
            'customer' => 'SUB',
            default => 'ND',
        });

        $tenantId = TenantResolver::currentTenantId();
        $last = FiberPlantNode::query()
            ->where('code', 'like', $prefix.'-%')
            ->orderByDesc('id')
            ->value('code');

        $num = 1;
        if (is_string($last) && preg_match('/-(\d+)$/', $last, $m)) {
            $num = ((int) $m[1]) + 1;
        }

        return sprintf('%s-%03d', $prefix, $num);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeNode(FiberPlantNode $node): array
    {
        $typeConfig = config('fiber_plant.node_types.'.$node->type, []);

        return [
            'id' => $node->id,
            'code' => $node->code,
            'name' => $node->name,
            'type' => $node->type,
            'type_label' => $node->typeLabel(),
            'color' => $typeConfig['color'] ?? '#64748b',
            'lat' => $node->latitude !== null ? (float) $node->latitude : null,
            'lng' => $node->longitude !== null ? (float) $node->longitude : null,
            'address' => $node->address,
            'splitter_ratio' => $node->splitter_ratio,
            'splitter_direction' => $node->splitter_direction,
            'bearing_deg' => $node->bearing_deg,
            'customer_id' => $node->customer_id,
            'device_id' => $node->device_id,
            'pop_box_id' => $node->pop_box_id,
            'notes' => $node->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEdge(FiberPlantEdge $edge): array
    {
        return [
            'id' => $edge->id,
            'from_node_id' => $edge->from_node_id,
            'to_node_id' => $edge->to_node_id,
            'from' => $edge->fromNode ? [(float) $edge->fromNode->latitude, (float) $edge->fromNode->longitude] : null,
            'to' => $edge->toNode ? [(float) $edge->toNode->latitude, (float) $edge->toNode->longitude] : null,
            'cable_type' => $edge->cable_type,
            'cable_type_label' => $edge->cableTypeLabel(),
            'fiber_count' => $edge->fiber_count,
            'cable_color' => $edge->cable_color,
            'cable_color_hex' => $edge->cableColorHex(),
            'tube_color' => $edge->tube_color,
            'length_m' => (float) $edge->length_m,
            'direction_label' => $edge->direction_label,
            'bearing_deg' => $edge->bearing_deg,
            'notes' => $edge->notes,
            'label' => trim(sprintf(
                '%s · %sm%s',
                $edge->cableTypeLabel(),
                number_format((float) $edge->length_m, 0),
                $edge->direction_label ? ' · '.$edge->direction_label : ''
            )),
        ];
    }

    /**
     * @return array{found: bool, total_length_m: float, segments: list<array<string, mixed>>, nodes: list<array<string, mixed>>}
     */
    private function traceFromNode(FiberPlantNode $start): array
    {
        $segments = [];
        $visitedNodes = [];
        $current = $start;
        $total = 0.0;
        $guard = 0;

        while ($current !== null && $guard < 50) {
            $guard++;
            $visitedNodes[] = $this->serializeNode($current);

            $edge = FiberPlantEdge::query()
                ->where('is_active', true)
                ->where('to_node_id', $current->id)
                ->with(['fromNode'])
                ->orderByDesc('id')
                ->first();

            if ($edge === null) {
                break;
            }

            $total += (float) $edge->length_m;
            $segments[] = [
                'edge_id' => $edge->id,
                'from' => $edge->fromNode?->name,
                'to' => $current->name,
                'length_m' => (float) $edge->length_m,
                'cable_color' => $edge->cable_color,
                'cable_color_hex' => $edge->cableColorHex(),
                'direction' => $edge->direction_label,
                'cable_type' => $edge->cableTypeLabel(),
            ];

            $current = $edge->fromNode;
            if ($current?->type === 'olt') {
                $visitedNodes[] = $this->serializeNode($current);
                break;
            }
        }

        return [
            'found' => count($segments) > 0,
            'total_length_m' => round($total, 2),
            'segments' => array_reverse($segments),
            'nodes' => array_reverse($visitedNodes),
        ];
    }

    /**
     * @return array{found: bool, total_length_m: float, segments: list<array<string, mixed>>, nodes: list<array<string, mixed>>}
     */
    private function traceFromCustomerMeta(Customer $customer): array
    {
        $cableLength = data_get($customer->meta, 'cable_length_m');

        return [
            'found' => is_numeric($cableLength),
            'total_length_m' => is_numeric($cableLength) ? (float) $cableLength : 0,
            'segments' => is_numeric($cableLength) ? [[
                'edge_id' => null,
                'from' => 'Splitter / POP',
                'to' => $customer->name,
                'length_m' => (float) $cableLength,
                'cable_color' => data_get($customer->meta, 'drop_cable_color', 'blue'),
                'cable_color_hex' => config('fiber_plant.cable_colors.'.data_get($customer->meta, 'drop_cable_color', 'blue').'.hex', '#2563eb'),
                'direction' => data_get($customer->meta, 'drop_direction'),
                'cable_type' => 'Drop',
            ]] : [],
            'nodes' => [],
        ];
    }

    /**
     * @param  Collection<int, FiberPlantNode>  $nodes
     * @param  Collection<int, FiberPlantEdge>  $edges
     * @return array<string, int|float>
     */
    private function stats(Collection $nodes, Collection $edges): array
    {
        return [
            'nodes' => $nodes->count(),
            'edges' => $edges->count(),
            'total_cable_m' => round((float) $edges->sum('length_m'), 2),
            'splitters' => $nodes->where('type', 'splitter')->count(),
            'customers' => $nodes->where('type', 'customer')->count(),
        ];
    }

    /**
     * @param  Collection<int, FiberPlantNode>  $nodes
     * @return array{lat: float, lng: float, zoom: int}
     */
    private function resolveCenter(Collection $nodes): array
    {
        $withCoords = $nodes->filter(fn (FiberPlantNode $n) => $n->hasCoordinates());

        if ($withCoords->isEmpty()) {
            return ['lat' => 23.8103, 'lng' => 90.4125, 'zoom' => 12];
        }

        $lat = (float) $withCoords->avg(fn (FiberPlantNode $n) => (float) $n->latitude);
        $lng = (float) $withCoords->avg(fn (FiberPlantNode $n) => (float) $n->longitude);

        return ['lat' => $lat, 'lng' => $lng, 'zoom' => 15];
    }

    private function estimateLengthM(FiberPlantNode $from, FiberPlantNode $to): float
    {
        if (! $from->hasCoordinates() || ! $to->hasCoordinates()) {
            return 0;
        }

        return round($this->haversineM(
            (float) $from->latitude,
            (float) $from->longitude,
            (float) $to->latitude,
            (float) $to->longitude,
        ), 2);
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
