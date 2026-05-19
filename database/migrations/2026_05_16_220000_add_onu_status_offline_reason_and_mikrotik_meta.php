<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('onu_oper_status', 24)->default('unknown')->after('last_polled_at');
            $table->text('offline_reason')->nullable()->after('onu_oper_status');
        });

        Schema::table('mikrotik_servers', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('last_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('mikrotik_servers', function (Blueprint $table) {
            $table->dropColumn('meta');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['onu_oper_status', 'offline_reason']);
        });
    }
};
