<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_office_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('radius_meters')->default(10);
            $table->json('allowed_ips')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->foreignId('attendance_office_location_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('attendance_office_locations')
                ->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable()->after('check_out');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedSmallInteger('accuracy_meters')->nullable()->after('longitude');
            $table->unsignedInteger('distance_meters')->nullable()->after('accuracy_meters');
            $table->string('client_ip', 45)->nullable()->after('distance_meters');
            $table->boolean('location_verified')->default(false)->after('client_ip');
            $table->boolean('geofence_override')->default(false)->after('location_verified');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('attendance_office_location_id');
            $table->dropColumn([
                'latitude',
                'longitude',
                'accuracy_meters',
                'distance_meters',
                'client_ip',
                'location_verified',
                'geofence_override',
            ]);
        });

        Schema::dropIfExists('attendance_office_locations');
    }
};
