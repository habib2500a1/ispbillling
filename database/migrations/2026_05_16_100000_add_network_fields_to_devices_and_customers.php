<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('network_access_state', 32)->default('active')->after('status');
            $table->string('radius_username')->nullable()->after('network_access_state');
            $table->index('network_access_state');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('olt_id')->nullable()->after('tenant_id')->constrained('devices')->nullOnDelete();
            $table->string('vendor', 32)->nullable()->after('type');
            $table->string('management_ip', 45)->nullable()->after('vendor');
            $table->string('onu_external_id', 64)->nullable()->after('management_ip');
            $table->unsignedSmallInteger('vlan_id')->nullable()->after('onu_external_id');
            $table->string('framed_ip_address', 45)->nullable()->after('vlan_id');
            $table->decimal('rx_power_dbm', 8, 3)->nullable()->after('framed_ip_address');
            $table->decimal('tx_power_dbm', 8, 3)->nullable()->after('rx_power_dbm');
            $table->timestamp('provisioned_at')->nullable()->after('tx_power_dbm');
            $table->timestamp('last_polled_at')->nullable()->after('provisioned_at');
            $table->json('meta')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('olt_id');
            $table->dropColumn([
                'vendor',
                'management_ip',
                'onu_external_id',
                'vlan_id',
                'framed_ip_address',
                'rx_power_dbm',
                'tx_power_dbm',
                'provisioned_at',
                'last_polled_at',
                'meta',
            ]);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['network_access_state']);
            $table->dropColumn(['network_access_state', 'radius_username']);
        });
    }
};
