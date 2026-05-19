<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('kyc_status', 32)->default('pending')->after('nid_number');
            $table->timestamp('kyc_verified_at')->nullable()->after('kyc_status');
            $table->text('kyc_notes')->nullable()->after('kyc_verified_at');
            $table->string('segment', 64)->nullable()->after('kyc_notes');
            $table->text('address')->nullable()->after('segment');
        });

        Schema::create('olt_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->unsignedSmallInteger('card_index');
            $table->unsignedSmallInteger('pon_index');
            $table->string('label')->nullable();
            $table->string('admin_status', 24)->default('enabled');
            $table->string('oper_status', 24)->default('unknown');
            $table->decimal('utilization_percent', 5, 2)->nullable();
            $table->unsignedInteger('fiber_distance_m')->nullable();
            $table->string('service_profile', 128)->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'card_index', 'pon_index']);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('olt_port_id')->nullable()->after('olt_id')->constrained('olt_ports')->nullOnDelete();
            $table->string('lease_status', 24)->default('none')->after('status');
            $table->decimal('lease_monthly_fee', 10, 2)->nullable()->after('lease_status');
            $table->timestamp('lease_started_at')->nullable()->after('lease_monthly_fee');
            $table->timestamp('lease_ended_at')->nullable()->after('lease_started_at');
            $table->boolean('mac_binding_strict')->default(false)->after('lease_ended_at');
            $table->boolean('serial_binding_strict')->default(false)->after('mac_binding_strict');
            $table->text('authorization_password')->nullable()->after('serial_binding_strict');
            $table->text('snmp_community')->nullable()->after('authorization_password');
            $table->string('snmp_version', 8)->default('v2c')->after('snmp_community');
            $table->unsignedSmallInteger('telnet_port')->nullable()->after('snmp_version');
            $table->unsignedSmallInteger('ssh_port')->nullable()->after('telnet_port');
            $table->string('ssh_username', 64)->nullable()->after('ssh_port');
            $table->json('olt_health')->nullable()->after('ssh_username');
            $table->timestamp('last_health_polled_at')->nullable()->after('olt_health');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('olt_port_id');
            $table->dropColumn([
                'lease_status',
                'lease_monthly_fee',
                'lease_started_at',
                'lease_ended_at',
                'mac_binding_strict',
                'serial_binding_strict',
                'authorization_password',
                'snmp_community',
                'snmp_version',
                'telnet_port',
                'ssh_port',
                'ssh_username',
                'olt_health',
                'last_health_polled_at',
            ]);
        });

        Schema::dropIfExists('olt_ports');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'kyc_status',
                'kyc_verified_at',
                'kyc_notes',
                'segment',
                'address',
            ]);
        });
    }
};
