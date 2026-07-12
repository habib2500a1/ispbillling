<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('vendor', 64)->nullable();
            $table->string('olt_driver', 48)->nullable();
            $table->string('model', 128)->nullable();
            $table->string('location')->nullable();
            $table->string('management_ip', 64)->nullable();
            $table->string('snmp_host', 64)->nullable();
            $table->unsignedSmallInteger('snmp_port')->default(161);
            $table->text('snmp_community')->nullable();
            $table->string('snmp_version', 8)->default('v2c');
            $table->unsignedSmallInteger('telnet_port')->nullable();
            $table->unsignedSmallInteger('ssh_port')->nullable();
            $table->string('ssh_username', 64)->nullable();
            $table->text('ssh_password')->nullable();
            $table->string('status', 24)->default('active');
            $table->json('olt_health')->nullable();
            $table->timestamp('last_health_polled_at')->nullable();
            $table->timestamp('last_snmp_poll_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('olt_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olt_id')->constrained('olts')->cascadeOnDelete();
            $table->unsignedSmallInteger('card_index')->default(0);
            $table->unsignedSmallInteger('pon_index');
            $table->string('label')->nullable();
            $table->string('admin_status', 24)->default('enabled');
            $table->string('oper_status', 24)->default('unknown');
            $table->decimal('utilization_percent', 5, 2)->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['olt_id', 'card_index', 'pon_index']);
        });

        Schema::create('olt_health_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olt_id')->constrained('olts')->cascadeOnDelete();
            $table->boolean('snmp_ok')->default(false);
            $table->unsignedTinyInteger('cpu_percent')->nullable();
            $table->unsignedTinyInteger('memory_percent')->nullable();
            $table->decimal('temperature_c', 5, 1)->nullable();
            $table->string('fan_status', 32)->nullable();
            $table->string('power_supply_status', 32)->nullable();
            $table->unsignedInteger('interfaces_up')->nullable();
            $table->unsignedInteger('interfaces_total')->nullable();
            $table->unsignedInteger('onus_online')->nullable();
            $table->unsignedInteger('onus_offline')->nullable();
            $table->unsignedInteger('pon_ports')->nullable();
            $table->unsignedBigInteger('sys_uptime_ticks')->nullable();
            $table->unsignedTinyInteger('health_score')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamp('sampled_at')->index();
            $table->timestamps();

            $table->index(['olt_id', 'sampled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('olt_health_logs');
        Schema::dropIfExists('olt_ports');
        Schema::dropIfExists('olts');
    }
};
