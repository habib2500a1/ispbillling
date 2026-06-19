<?php

use App\Support\Gis\PostgisSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql' || ! PostgisSupport::enabled()) {
            return;
        }

        DB::statement(<<<'SQL'
CREATE OR REPLACE VIEW gis_mvt_customers AS
SELECT
    c.id,
    c.tenant_id,
    c.status,
    c.is_ppp_online,
    c.network_access_state,
    COALESCE((c.meta->>'tag_vip')::boolean, false) AS is_vip,
    c.geom
FROM customers c
WHERE c.geom IS NOT NULL
  AND c.status <> 'terminated'
SQL);

        DB::statement(<<<'SQL'
CREATE OR REPLACE VIEW gis_mvt_plant_nodes AS
SELECT
    n.id,
    n.tenant_id,
    n.type,
    n.name,
    n.code,
    n.is_active,
    n.geom
FROM fiber_plant_nodes n
WHERE n.geom IS NOT NULL
  AND n.is_active = true
SQL);

        DB::statement(<<<'SQL'
CREATE OR REPLACE VIEW gis_mvt_pop_boxes AS
SELECT
    p.id,
    p.tenant_id,
    p.code,
    p.name,
    p.is_active,
    p.geom
FROM pop_boxes p
WHERE p.geom IS NOT NULL
  AND p.is_active = true
SQL);

        DB::statement(<<<'SQL'
CREATE OR REPLACE VIEW gis_mvt_field_staff AS
SELECT DISTINCT ON (f.user_id)
    f.id,
    f.tenant_id,
    f.user_id,
    f.latitude,
    f.longitude,
    f.recorded_at,
    f.geom
FROM field_staff_locations f
ORDER BY f.user_id, f.recorded_at DESC
SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['gis_mvt_field_staff', 'gis_mvt_pop_boxes', 'gis_mvt_plant_nodes', 'gis_mvt_customers'] as $view) {
            DB::statement("DROP VIEW IF EXISTS {$view}");
        }
    }
};
