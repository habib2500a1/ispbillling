<?php

namespace App\Console\Commands;

use App\Support\Gis\PostgisSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnablePostgisCommand extends Command
{
    protected $signature = 'isp:enable-postgis';

    protected $description = 'Enable PostGIS extension and geom columns (run after switching postgres image to postgis/postgis)';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->error('PostgreSQL required.');

            return self::FAILURE;
        }

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        } catch (\Throwable $e) {
            $this->error('PostGIS extension not available: '.$e->getMessage());
            $this->line('Switch docker postgres image to postgis/postgis:16-3.4 and recreate the container.');

            return self::FAILURE;
        }

        PostgisSupport::forgetCache();
        $this->info('PostGIS extension enabled.');

        $this->ensureGeomColumns();
        $this->ensureVectorViews();

        Artisan::call('isp:sync-gis-geom');
        $this->line(Artisan::output());

        $this->info('PostGIS GIS stack ready. Start pg_tileserv: docker compose up -d pg_tileserv');

        return self::SUCCESS;
    }

    private function ensureGeomColumns(): void
    {
        if (Schema::hasTable('customers') && ! Schema::hasColumn('customers', 'geom')) {
            DB::statement('ALTER TABLE customers ADD COLUMN geom geometry(Point, 4326)');
            DB::statement('CREATE INDEX IF NOT EXISTS customers_geom_gix ON customers USING GIST (geom)');
            DB::statement('CREATE INDEX IF NOT EXISTS customers_tenant_geom_gix ON customers (tenant_id) WHERE geom IS NOT NULL');
            $this->line('customers.geom added');
        }

        if (Schema::hasTable('fiber_plant_nodes') && ! Schema::hasColumn('fiber_plant_nodes', 'geom')) {
            DB::statement('ALTER TABLE fiber_plant_nodes ADD COLUMN geom geometry(Point, 4326)');
            DB::statement('CREATE INDEX IF NOT EXISTS fiber_plant_nodes_geom_gix ON fiber_plant_nodes USING GIST (geom)');
            $this->line('fiber_plant_nodes.geom added');
        }

        if (Schema::hasTable('pop_boxes') && ! Schema::hasColumn('pop_boxes', 'geom')) {
            DB::statement('ALTER TABLE pop_boxes ADD COLUMN geom geometry(Point, 4326)');
            DB::statement('CREATE INDEX IF NOT EXISTS pop_boxes_geom_gix ON pop_boxes USING GIST (geom)');
            $this->line('pop_boxes.geom added');
        }

        if (Schema::hasTable('areas') && ! Schema::hasColumn('areas', 'geom')) {
            DB::statement('ALTER TABLE areas ADD COLUMN geom geometry(Geometry, 4326)');
            DB::statement('CREATE INDEX IF NOT EXISTS areas_geom_gix ON areas USING GIST (geom)');
            $this->line('areas.geom added');
        }

        if (Schema::hasTable('field_staff_locations') && ! Schema::hasColumn('field_staff_locations', 'geom')) {
            DB::statement('ALTER TABLE field_staff_locations ADD COLUMN geom geometry(Point, 4326)');
            DB::statement('CREATE INDEX IF NOT EXISTS field_staff_locations_geom_gix ON field_staff_locations USING GIST (geom)');
            $this->line('field_staff_locations.geom added');
        }
    }

    private function ensureVectorViews(): void
    {
        DB::statement(<<<'SQL'
CREATE OR REPLACE VIEW gis_mvt_customers AS
SELECT c.id, c.tenant_id, c.status, c.is_ppp_online, c.network_access_state,
       COALESCE((c.meta->>'tag_vip')::boolean, false) AS is_vip, c.geom
FROM customers c
WHERE c.geom IS NOT NULL AND c.status <> 'terminated'
SQL);
        DB::statement(<<<'SQL'
CREATE OR REPLACE VIEW gis_mvt_plant_nodes AS
SELECT n.id, n.tenant_id, n.type, n.name, n.code, n.is_active, n.geom
FROM fiber_plant_nodes n
WHERE n.geom IS NOT NULL AND n.is_active = true
SQL);
        DB::statement(<<<'SQL'
CREATE OR REPLACE VIEW gis_mvt_pop_boxes AS
SELECT p.id, p.tenant_id, p.code, p.name, p.is_active, p.geom
FROM pop_boxes p
WHERE p.geom IS NOT NULL AND p.is_active = true
SQL);
        DB::statement(<<<'SQL'
CREATE OR REPLACE VIEW gis_mvt_field_staff AS
SELECT DISTINCT ON (f.user_id) f.id, f.tenant_id, f.user_id, f.latitude, f.longitude, f.recorded_at, f.geom
FROM field_staff_locations f
ORDER BY f.user_id, f.recorded_at DESC
SQL);
        $this->line('Vector tile views refreshed');
    }
}
