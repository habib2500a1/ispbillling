<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cpe_device_logs');
        Schema::dropIfExists('cpe_firmwares');
        Schema::dropIfExists('cpe_devices');
        Schema::dropIfExists('genieacs_devices');

        if (Schema::hasTable('packages') && Schema::hasColumn('packages', 'max_devices')) {
            Schema::table('packages', function (Blueprint $table): void {
                $table->dropColumn('max_devices');
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty — GenieACS module was removed from the application.
    }
};
