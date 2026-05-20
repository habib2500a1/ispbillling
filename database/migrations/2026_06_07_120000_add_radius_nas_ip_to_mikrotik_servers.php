<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mikrotik_servers', function (Blueprint $table): void {
            $table->string('radius_nas_ip', 45)->nullable()->after('host');
        });
    }

    public function down(): void
    {
        Schema::table('mikrotik_servers', function (Blueprint $table): void {
            $table->dropColumn('radius_nas_ip');
        });
    }
};
