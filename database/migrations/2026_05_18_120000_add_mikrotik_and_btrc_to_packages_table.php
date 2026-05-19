<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->foreignId('mikrotik_server_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('mikrotik_servers')
                ->nullOnDelete();
            $table->string('mikrotik_profile_name', 128)->nullable()->after('name');
            $table->timestamp('mikrotik_synced_at')->nullable()->after('mikrotik_profile_name');
            $table->json('mikrotik_sync_meta')->nullable()->after('mikrotik_synced_at');
            $table->string('btrc_label', 255)->nullable()->after('mikrotik_sync_meta');
            $table->string('btrc_bandwidth', 128)->nullable()->after('btrc_label');
            $table->text('btrc_notes')->nullable()->after('btrc_bandwidth');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->index(['tenant_id', 'mikrotik_server_id', 'mikrotik_profile_name'], 'packages_mikrotik_profile_idx');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropIndex('packages_mikrotik_profile_idx');
            $table->dropConstrainedForeignId('mikrotik_server_id');
            $table->dropColumn([
                'mikrotik_profile_name',
                'mikrotik_synced_at',
                'mikrotik_sync_meta',
                'btrc_label',
                'btrc_bandwidth',
                'btrc_notes',
            ]);
        });
    }
};
