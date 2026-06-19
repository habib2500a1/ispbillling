<?php

namespace App\Console\Commands;

use App\Support\Gis\PostgisSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncGisGeomCommand extends Command
{
    protected $signature = 'isp:sync-gis-geom {--tenant= : Limit to tenant id} {--chunk=2000 : Rows per batch}';

    protected $description = 'Sync PostGIS geom columns from latitude/longitude and customer meta GPS';

    public function handle(): int
    {
        if (! PostgisSupport::enabled()) {
            $this->warn('PostGIS extension not available — skipping geom sync.');

            return self::SUCCESS;
        }

        $tenantId = $this->option('tenant');
        $chunk = max(100, (int) $this->option('chunk'));

        $this->syncCustomers($tenantId, $chunk);
        $this->syncFiberNodes($tenantId, $chunk);
        $this->syncPopBoxes($tenantId, $chunk);
        $this->syncFieldStaff($tenantId, $chunk);
        $this->syncAreaBoundaries($tenantId);

        $this->info('GIS geom sync complete.');

        return self::SUCCESS;
    }

    private function syncCustomers(mixed $tenantId, int $chunk): void
    {
        $query = DB::table('customers')
            ->whereRaw("meta->>'gps_lat' IS NOT NULL AND meta->>'gps_lng' IS NOT NULL")
            ->where(function ($q): void {
                $q->whereNull('geom')
                    ->orWhereRaw("geom IS DISTINCT FROM ST_SetSRID(ST_MakePoint((meta->>'gps_lng')::double precision, (meta->>'gps_lat')::double precision), 4326)");
            });

        if ($tenantId) {
            $query->where('tenant_id', (int) $tenantId);
        }

        $updated = 0;
        $query->orderBy('id')->chunkById($chunk, function ($rows) use (&$updated): void {
            foreach ($rows as $row) {
                $lat = (float) data_get(json_decode($row->meta ?? '{}', true), 'gps_lat');
                $lng = (float) data_get(json_decode($row->meta ?? '{}', true), 'gps_lng');
                if ($lat === 0.0 && $lng === 0.0) {
                    continue;
                }

                DB::update(
                    'UPDATE customers SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                    [$lng, $lat, $row->id],
                );
                $updated++;
            }
        });

        $this->line("Customers geom: {$updated}");
    }

    private function syncFiberNodes(mixed $tenantId, int $chunk): void
    {
        $query = DB::table('fiber_plant_nodes')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($tenantId) {
            $query->where('tenant_id', (int) $tenantId);
        }

        $updated = 0;
        $query->orderBy('id')->chunkById($chunk, function ($rows) use (&$updated): void {
            foreach ($rows as $row) {
                DB::update(
                    'UPDATE fiber_plant_nodes SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                    [(float) $row->longitude, (float) $row->latitude, $row->id],
                );
                $updated++;
            }
        });

        $this->line("Fiber nodes geom: {$updated}");
    }

    private function syncPopBoxes(mixed $tenantId, int $chunk): void
    {
        $query = DB::table('pop_boxes')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($tenantId) {
            $query->where('tenant_id', (int) $tenantId);
        }

        $updated = 0;
        $query->orderBy('id')->chunkById($chunk, function ($rows) use (&$updated): void {
            foreach ($rows as $row) {
                DB::update(
                    'UPDATE pop_boxes SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                    [(float) $row->longitude, (float) $row->latitude, $row->id],
                );
                $updated++;
            }
        });

        $this->line("POP boxes geom: {$updated}");
    }

    private function syncFieldStaff(mixed $tenantId, int $chunk): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('field_staff_locations')) {
            return;
        }

        $query = DB::table('field_staff_locations');

        if ($tenantId) {
            $query->where('tenant_id', (int) $tenantId);
        }

        $updated = 0;
        $query->orderBy('id')->chunkById($chunk, function ($rows) use (&$updated): void {
            foreach ($rows as $row) {
                DB::update(
                    'UPDATE field_staff_locations SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                    [(float) $row->longitude, (float) $row->latitude, $row->id],
                );
                $updated++;
            }
        });

        $this->line("Field staff geom: {$updated}");
    }

    private function syncAreaBoundaries(mixed $tenantId): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('areas', 'boundary')) {
            return;
        }

        $query = DB::table('areas')->whereNotNull('boundary');

        if ($tenantId) {
            $query->where('tenant_id', (int) $tenantId);
        }

        $updated = 0;
        foreach ($query->get(['id', 'boundary']) as $row) {
            $geo = json_decode($row->boundary ?? 'null', true);
            if (! is_array($geo)) {
                continue;
            }

            $wkt = $this->geoJsonToWkt($geo);
            if ($wkt === null) {
                continue;
            }

            DB::update(
                'UPDATE areas SET geom = ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) WHERE id = ?',
                [json_encode($geo), $row->id],
            );
            $updated++;
        }

        $this->line("Area boundaries geom: {$updated}");
    }

    /**
     * @param  array<string, mixed>  $geo
     */
    private function geoJsonToWkt(array $geo): ?string
    {
        $type = $geo['type'] ?? null;
        if (! in_array($type, ['Polygon', 'MultiPolygon'], true)) {
            return null;
        }

        return $type;
    }
}
