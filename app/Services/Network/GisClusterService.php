<?php

namespace App\Services\Network;

use App\Models\Customer;
use App\Support\CustomerStatus;
use App\Support\Gis\PostgisSupport;
use App\Support\TenantResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Server-side grid clustering for large ISP maps (500k+ subscribers).
 * Never return all markers at low zoom — aggregate into buckets.
 */
final class GisClusterService
{
    /**
     * @return array{
     *     mode: string,
     *     zoom: int,
     *     cell_deg: float,
     *     clusters: list<array<string, mixed>>,
     *     total_in_view: int
     * }
     */
    public function clusters(float $north, float $south, float $east, float $west, int $zoom): array
    {
        $tenantId = TenantResolver::requiredTenantId();
        $zoom = max(1, min(20, $zoom));
        $cellDeg = $this->cellDegrees($zoom);

        if (PostgisSupport::customersHaveGeom()) {
            return $this->clustersPostgis($tenantId, $north, $south, $east, $west, $zoom, $cellDeg);
        }

        return $this->clustersLegacy($tenantId, $north, $south, $east, $west, $zoom, $cellDeg);
    }

    /**
     * @return array{
     *     mode: string,
     *     zoom: int,
     *     cell_deg: float,
     *     clusters: list<array<string, mixed>>,
     *     total_in_view: int
     * }
     */
    private function clustersPostgis(int $tenantId, float $north, float $south, float $east, float $west, int $zoom, float $cellDeg): array
    {
        $rows = DB::select(<<<'SQL'
SELECT
    ST_Y(ST_Centroid(ST_Collect(geom))) AS lat,
    ST_X(ST_Centroid(ST_Collect(geom))) AS lng,
    COUNT(*)::int AS count,
    SUM(CASE WHEN is_ppp_online THEN 1 ELSE 0 END)::int AS online,
    SUM(CASE WHEN NOT is_ppp_online AND COALESCE(network_access_state, 'active') <> 'suspended' THEN 1 ELSE 0 END)::int AS offline,
    SUM(CASE WHEN COALESCE(network_access_state, 'active') = 'suspended' THEN 1 ELSE 0 END)::int AS suspended,
    SUM(CASE WHEN COALESCE((meta->>'tag_vip')::boolean, false) THEN 1 ELSE 0 END)::int AS vip
FROM customers
WHERE tenant_id = ?
  AND status <> ?
  AND geom IS NOT NULL
  AND geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)
GROUP BY ST_SnapToGrid(geom, ?)
SQL, [$tenantId, CustomerStatus::TERMINATED, $west, $south, $east, $north, $cellDeg]);

        $clusters = collect($rows)
            ->sortByDesc('count')
            ->values()
            ->map(fn ($row): array => [
                'lat' => (float) $row->lat,
                'lng' => (float) $row->lng,
                'count' => (int) $row->count,
                'online' => (int) $row->online,
                'offline' => (int) $row->offline,
                'suspended' => (int) $row->suspended,
                'vip' => (int) $row->vip,
                'label' => number_format((int) $row->count).' subscribers',
                'severity' => match (true) {
                    (int) $row->offline >= 200 => 'critical',
                    (int) $row->offline >= 50 => 'warning',
                    default => 'ok',
                },
            ])
            ->all();

        $total = (int) DB::table('customers')
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', CustomerStatus::TERMINATED)
            ->whereNotNull('geom')
            ->whereRaw('geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)', [$west, $south, $east, $north])
            ->count();

        return [
            'mode' => 'cluster_postgis',
            'zoom' => $zoom,
            'cell_deg' => $cellDeg,
            'clusters' => $clusters,
            'total_in_view' => $total,
        ];
    }

    /**
     * @return array{
     *     mode: string,
     *     zoom: int,
     *     cell_deg: float,
     *     clusters: list<array<string, mixed>>,
     *     total_in_view: int
     * }
     */
    private function clustersLegacy(int $tenantId, float $north, float $south, float $east, float $west, int $zoom, float $cellDeg): array
    {
        $customers = Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', CustomerStatus::TERMINATED)
            ->whereRaw("meta->>'gps_lat' IS NOT NULL AND meta->>'gps_lng' IS NOT NULL")
            ->whereRaw("(meta->>'gps_lat')::double precision BETWEEN ? AND ?", [$south, $north])
            ->whereRaw("(meta->>'gps_lng')::double precision BETWEEN ? AND ?", [$west, $east])
            ->limit((int) config('gis.api.bbox_max_customers', 100_000))
            ->get(['id', 'meta', 'status', 'is_ppp_online', 'network_access_state']);

        $buckets = [];

        foreach ($customers as $customer) {
            $lat = (float) data_get($customer->meta, 'gps_lat');
            $lng = (float) data_get($customer->meta, 'gps_lng');

            if ($lat < $south || $lat > $north || $lng < $west || $lng > $east) {
                continue;
            }

            $gridLat = (int) floor($lat / $cellDeg);
            $gridLng = (int) floor($lng / $cellDeg);
            $key = $gridLat.'|'.$gridLng;

            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'grid_lat' => $gridLat,
                    'grid_lng' => $gridLng,
                    'lat' => ($gridLat + 0.5) * $cellDeg,
                    'lng' => ($gridLng + 0.5) * $cellDeg,
                    'count' => 0,
                    'online' => 0,
                    'offline' => 0,
                    'suspended' => 0,
                    'vip' => 0,
                    'onu_offline' => 0,
                ];
            }

            $buckets[$key]['count']++;

            if (! empty(data_get($customer->meta, 'tag_vip'))) {
                $buckets[$key]['vip']++;
            }

            if (($customer->network_access_state ?? 'active') === 'suspended') {
                $buckets[$key]['suspended']++;
            } elseif ($customer->is_ppp_online) {
                $buckets[$key]['online']++;
            } else {
                $buckets[$key]['offline']++;
            }
        }

        $clusters = collect($buckets)
            ->sortByDesc('count')
            ->values()
            ->map(fn (array $row): array => [
                ...$row,
                'label' => number_format($row['count']).' subscribers',
                'severity' => match (true) {
                    $row['offline'] >= 200 => 'critical',
                    $row['offline'] >= 50 => 'warning',
                    default => 'ok',
                },
            ])
            ->all();

        return [
            'mode' => 'cluster',
            'zoom' => $zoom,
            'cell_deg' => $cellDeg,
            'clusters' => $clusters,
            'total_in_view' => $customers->count(),
        ];
    }

    /**
     * Geographic outage areas — grid cells with many offline ONUs/PPP (NOC heat).
     *
     * @param  Collection<int, array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    public function outageAreas(Collection $nodes, int $minOffline = 50): array
    {
        $cellDeg = 0.02;
        $buckets = [];

        foreach ($nodes->where('type', 'customer') as $node) {
            if ($node['lat'] === null || $node['lng'] === null) {
                continue;
            }

            $status = $node['status'] ?? 'unknown';
            if (! in_array($status, ['ppp_offline', 'onu_offline'], true)) {
                continue;
            }

            $lat = (float) $node['lat'];
            $lng = (float) $node['lng'];
            $gridLat = (int) floor($lat / $cellDeg);
            $gridLng = (int) floor($lng / $cellDeg);
            $key = $gridLat.'|'.$gridLng;

            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'lat' => ($gridLat + 0.5) * $cellDeg,
                    'lng' => ($gridLng + 0.5) * $cellDeg,
                    'offline_count' => 0,
                    'onu_offline' => 0,
                    'ppp_offline' => 0,
                    'area_label' => $node['ops']['zone'] ?? $node['ops']['subzone'] ?? null,
                ];
            }

            $buckets[$key]['offline_count']++;
            if ($status === 'onu_offline') {
                $buckets[$key]['onu_offline']++;
            } else {
                $buckets[$key]['ppp_offline']++;
            }

            if (empty($buckets[$key]['area_label']) && ! empty($node['ops']['zone'])) {
                $buckets[$key]['area_label'] = $node['ops']['zone'];
            }
        }

        return collect($buckets)
            ->filter(fn (array $b): bool => $b['offline_count'] >= $minOffline)
            ->sortByDesc('offline_count')
            ->values()
            ->map(fn (array $b): array => [
                ...$b,
                'title' => ($b['area_label'] ?: 'Outage zone').' · '.$b['offline_count'].' offline',
                'severity' => $b['offline_count'] >= 200 ? 'critical' : 'warning',
            ])
            ->all();
    }

    private function cellDegrees(int $zoom): float
    {
        return match (true) {
            $zoom <= 8 => 0.45,
            $zoom <= 10 => 0.12,
            $zoom <= 12 => 0.035,
            $zoom <= 14 => 0.012,
            $zoom <= 16 => 0.004,
            default => 0.0015,
        };
    }
}
