<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olt_health_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
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

            $table->index(['tenant_id', 'device_id', 'sampled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('olt_health_logs');
    }
};
