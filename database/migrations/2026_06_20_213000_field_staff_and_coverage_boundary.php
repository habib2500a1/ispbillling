<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('field_staff_locations')) {
            Schema::create('field_staff_locations', function (Blueprint $table): void {
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

        if (Schema::hasTable('areas') && ! Schema::hasColumn('areas', 'boundary')) {
            Schema::table('areas', function (Blueprint $table): void {
                $table->json('boundary')->nullable()->after('code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('areas') && Schema::hasColumn('areas', 'boundary')) {
            Schema::table('areas', function (Blueprint $table): void {
                $table->dropColumn('boundary');
            });
        }

        Schema::dropIfExists('field_staff_locations');
    }
};
