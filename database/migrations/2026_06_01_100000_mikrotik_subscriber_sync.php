<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('mikrotik_secret_name', 128)->nullable()->after('radius_username');
            $table->foreignId('mikrotik_server_id')->nullable()->after('mikrotik_secret_name')->constrained('mikrotik_servers')->nullOnDelete();
            $table->timestamp('mikrotik_synced_at')->nullable()->after('mikrotik_server_id');

            $table->index(['tenant_id', 'mikrotik_secret_name']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['mikrotik_server_id']);
            $table->dropColumn(['mikrotik_secret_name', 'mikrotik_server_id', 'mikrotik_synced_at']);
        });
    }
};
