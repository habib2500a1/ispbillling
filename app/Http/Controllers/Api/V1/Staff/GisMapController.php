<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Services\Network\FiberPlantMapService;
use App\Services\Network\GisClusterService;
use App\Services\Network\GisIntelligenceOpsService;
use App\Support\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GisMapController extends Controller
{
    public function map(Request $request, FiberPlantMapService $mapService): JsonResponse
    {
        $customerId = $request->integer('customer');
        $payload = $mapService->buildPayload($customerId > 0 ? $customerId : null);

        $north = $request->input('north');
        $south = $request->input('south');
        $east = $request->input('east');
        $west = $request->input('west');

        if (is_numeric($north) && is_numeric($south) && is_numeric($east) && is_numeric($west)) {
            $payload = $this->filterByBbox($payload, (float) $north, (float) $south, (float) $east, (float) $west);
        }

        return response()->json([
            'ok' => true,
            'payload' => $payload,
            'refreshed_at' => data_get($payload, 'ops.refreshed_at'),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['ok' => true, 'results' => []]);
        }

        $payload = app(FiberPlantMapService::class)->buildPayload();
        $needle = mb_strtolower($q);
        $results = [];

        foreach ($payload['ops']['search_index'] ?? [] as $row) {
            $hay = mb_strtolower(implode(' ', array_filter([
                $row['label'] ?? '',
                $row['login'] ?? '',
                $row['code'] ?? '',
                $row['phone'] ?? '',
            ])));

            if (! str_contains($hay, $needle)) {
                continue;
            }

            $results[] = [
                'type' => 'customer',
                'id' => $row['id'],
                'label' => $row['label'],
                'login' => $row['login'] ?? null,
                'code' => $row['code'] ?? null,
                'lat' => $row['lat'] ?? null,
                'lng' => $row['lng'] ?? null,
                'on_map' => (bool) ($row['on_map'] ?? false),
                'node_id' => $row['node_id'] ?? null,
            ];

            if (count($results) >= 25) {
                break;
            }
        }

        foreach ($payload['nodes'] ?? [] as $node) {
            if (($node['type'] ?? '') === 'customer') {
                continue;
            }

            $hay = mb_strtolower(implode(' ', array_filter([
                $node['name'] ?? '',
                $node['code'] ?? '',
                $node['pon_label'] ?? '',
                $node['type'] ?? '',
            ])));

            if (! str_contains($hay, $needle)) {
                continue;
            }

            $results[] = [
                'type' => 'node',
                'id' => $node['id'] ?? null,
                'label' => $node['name'] ?? $node['code'] ?? 'Node',
                'node_type' => $node['type'] ?? 'other',
                'lat' => $node['lat'] ?? null,
                'lng' => $node['lng'] ?? null,
            ];

            if (count($results) >= 30) {
                break;
            }
        }

        return response()->json(['ok' => true, 'results' => $results]);
    }

    public function clusters(Request $request, GisClusterService $clusters): JsonResponse
    {
        $north = $request->input('north');
        $south = $request->input('south');
        $east = $request->input('east');
        $west = $request->input('west');
        $zoom = $request->integer('zoom', 12);

        if (! is_numeric($north) || ! is_numeric($south) || ! is_numeric($east) || ! is_numeric($west)) {
            return response()->json([
                'ok' => false,
                'message' => 'north, south, east, west query params required',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            ...$clusters->clusters((float) $north, (float) $south, (float) $east, (float) $west, $zoom),
        ]);
    }

    public function dependency(int $customerId, GisIntelligenceOpsService $gis): JsonResponse
    {
        TenantResolver::requiredTenantId();

        return response()->json($gis->dependencyTree($customerId));
    }

    public function rca(int $customerId, GisIntelligenceOpsService $gis): JsonResponse
    {
        TenantResolver::requiredTenantId();

        return response()->json($gis->rcaForCustomer($customerId));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function filterByBbox(array $payload, float $north, float $south, float $east, float $west): array
    {
        $inBox = static function (?float $lat, ?float $lng) use ($north, $south, $east, $west): bool {
            if ($lat === null || $lng === null) {
                return false;
            }

            return $lat <= $north && $lat >= $south && $lng <= $east && $lng >= $west;
        };

        $max = (int) config('gis.api.bbox_max_nodes', 5000);
        $nodes = collect($payload['nodes'] ?? [])
            ->filter(fn (array $n) => $inBox(
                isset($n['lat']) ? (float) $n['lat'] : null,
                isset($n['lng']) ? (float) $n['lng'] : null,
            ))
            ->take($max)
            ->values()
            ->all();

        $nodeIds = collect($nodes)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        $edges = collect($payload['edges'] ?? [])
            ->filter(function (array $edge) use ($nodeIds): bool {
                $from = (int) ($edge['from_node_id'] ?? 0);
                $to = (int) ($edge['to_node_id'] ?? 0);

                return in_array($from, $nodeIds, true) || in_array($to, $nodeIds, true);
            })
            ->values()
            ->all();

        $payload['nodes'] = $nodes;
        $payload['edges'] = $edges;
        $payload['bbox'] = compact('north', 'south', 'east', 'west');

        return $payload;
    }
}
