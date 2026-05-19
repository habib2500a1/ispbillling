<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snmp_poll_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('poll_type', 32)->default('olt');
            $table->boolean('success')->default(false);
            $table->string('gpon_profile', 64)->nullable();
            $table->unsignedInteger('sys_uptime_ticks')->nullable();
            $table->unsignedSmallInteger('interfaces_total')->default(0);
            $table->unsignedSmallInteger('interfaces_up')->default(0);
            $table->unsignedInteger('onus_online')->default(0);
            $table->unsignedInteger('onus_offline')->default(0);
            $table->unsignedInteger('pon_ports')->default(0);
            $table->text('error_message')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamp('polled_at');
            $table->timestamps();

            $table->index(['device_id', 'polled_at']);
        });

        Schema::create('netflow_exporters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('host', 45);
            $table->unsignedSmallInteger('port')->default(2055);
            $table->string('protocol', 16)->default('netflow_v5');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('netflow_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('netflow_exporter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('exporter_ip', 45)->nullable();
            $table->string('src_ip', 45);
            $table->string('dst_ip', 45);
            $table->unsignedSmallInteger('src_port')->nullable();
            $table->unsignedSmallInteger('dst_port')->nullable();
            $table->string('protocol', 16)->nullable();
            $table->unsignedBigInteger('bytes')->default(0);
            $table->unsignedBigInteger('packets')->default(0);
            $table->timestamp('flow_start')->nullable();
            $table->timestamp('flow_end')->nullable();
            $table->timestamp('sampled_at');
            $table->timestamps();

            $table->index(['tenant_id', 'sampled_at']);
            $table->index(['src_ip', 'sampled_at']);
            $table->index(['dst_ip', 'sampled_at']);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->string('gpon_profile', 64)->nullable()->after('olt_driver');
            $table->timestamp('last_snmp_poll_at')->nullable()->after('last_health_polled_at');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['gpon_profile', 'last_snmp_poll_at']);
        });

        Schema::dropIfExists('netflow_flows');
        Schema::dropIfExists('netflow_exporters');
        Schema::dropIfExists('snmp_poll_logs');
    }
};
