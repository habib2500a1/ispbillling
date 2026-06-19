<?php

use App\Support\Gis\PostgisSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasTable('field_staff_locations')) {
            Schema::create('field_staff_locations', function ($table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->unsignedSmallInteger('accuracy_meters')->nullable();
                $table->decimal('heading_deg', 5, 2)->nullable();
                $table->decimal('speed_kmh', 6, 2)->nullable();
                $table->timestamp('recorded_at');
                $table->timestamps();
                $table->index(['tenant_id', 'user_id', 'recorded_at']);
            });
        }

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        } catch (\Throwable $e) {
            report($e);

            return;
        }

        PostgisSupport::forgetCache();

        if (Schema::hasTable('customers') && ! Schema::hasColumn('customers', 'geom')) {
            DB::statement('ALTER TABLE customers ADD COLUMN geom geometry(Point, 4326)');
            DB::statement('CREATE INDEX customers_geom_gix ON customers USING GIST (geom)');
            DB::statement('CREATE INDEX customers_tenant_geom_gix ON customers (tenant_id) WHERE geom IS NOT NULL');
        }

        if (Schema::hasTable('fiber_plant_nodes') && ! Schema::hasColumn('fiber_plant_nodes', 'geom')) {
            DB::statement('ALTER TABLE fiber_plant_nodes ADD COLUMN geom geometry(Point, 4326)');
            DB::statement('CREATE INDEX fiber_plant_nodes_geom_gix ON fiber_plant_nodes USING GIST (geom)');
        }

        if (Schema::hasTable('pop_boxes') && ! Schema::hasColumn('pop_boxes', 'geom')) {
            DB::statement('ALTER TABLE pop_boxes ADD COLUMN geom geometry(Point, 4326)');
            DB::statement('CREATE INDEX pop_boxes_geom_gix ON pop_boxes USING GIST (geom)');
        }

        if (Schema::hasTable('areas') && ! Schema::hasColumn('areas', 'boundary')) {
            Schema::table('areas', function ($table): void {
                $table->json('boundary')->nullable()->after('code');
            });
        }

        if (Schema::hasTable('areas') && ! Schema::hasColumn('areas', 'geom')) {
            DB::statement('ALTER TABLE areas ADD COLUMN geom geometry(Geometry, 4326)');
            DB::statement('CREATE INDEX areas_geom_gix ON areas USING GIST (geom)');
        }

        if (Schema::hasTable('field_staff_locations') && ! Schema::hasColumn('field_staff_locations', 'geom')) {
            DB::statement('ALTER TABLE field_staff_locations ADD COLUMN geom geometry(Point, 4326)');
            DB::statement('CREATE INDEX field_staff_locations_geom_gix ON field_staff_locations USING GIST (geom)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['field_staff_locations', 'areas', 'pop_boxes', 'fiber_plant_nodes', 'customers'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'geom')) {
                continue;
            }

            DB::statement("DROP INDEX IF EXISTS {$table}_geom_gix");
            if ($table === 'customers') {
                DB::statement('DROP INDEX IF EXISTS customers_tenant_geom_gix');
            }
            Schema::table($table, function ($blueprint): void {
                $blueprint->dropColumn('geom');
            });
        }

        if (Schema::hasTable('areas') && Schema::hasColumn('areas', 'boundary')) {
            Schema::table('areas', function ($table): void {
                $table->dropColumn('boundary');
            });
        }

        PostgisSupport::forgetCache();
    }
};
