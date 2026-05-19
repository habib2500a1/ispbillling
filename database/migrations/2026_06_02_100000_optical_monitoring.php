<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onu_signal_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('olt_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('olt_port_id')->nullable()->constrained('olt_ports')->nullOnDelete();
            $table->decimal('rx_power_dbm', 8, 3)->nullable();
            $table->decimal('tx_power_dbm', 8, 3)->nullable();
            $table->string('rx_level', 24)->nullable();
            $table->string('tx_level', 24)->nullable();
            $table->string('onu_oper_status', 32)->nullable();
            $table->unsignedTinyInteger('health_score')->nullable();
            $table->string('granularity', 16)->default('snapshot');
            $table->timestamp('sampled_at')->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'sampled_at']);
            $table->index(['tenant_id', 'sampled_at']);
        });

        Schema::create('onu_health_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('device_id')->unique()->constrained('devices')->cascadeOnDelete();
            $table->unsignedTinyInteger('health_score')->default(0);
            $table->unsignedTinyInteger('stability_score')->default(0);
            $table->string('rx_level', 24)->nullable();
            $table->string('root_cause_hint', 64)->nullable();
            $table->decimal('rx_trend_dbm', 8, 3)->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('signal_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('olt_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('olt_port_id')->nullable()->constrained('olt_ports')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('support_ticket_id')->nullable()->constrained('support_tickets')->nullOnDelete();
            $table->string('alert_type', 48);
            $table->string('severity', 16)->default('warning');
            $table->string('title');
            $table->text('message')->nullable();
            $table->decimal('rx_power_dbm', 8, 3)->nullable();
            $table->decimal('tx_power_dbm', 8, 3)->nullable();
            $table->string('status', 16)->default('open');
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'detected_at']);
            $table->index(['alert_type', 'status']);
        });

        Schema::create('pon_signal_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('olt_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('olt_port_id')->nullable()->constrained('olt_ports')->nullOnDelete();
            $table->unsignedSmallInteger('card_no')->nullable();
            $table->unsignedSmallInteger('pon_no')->nullable();
            $table->unsignedInteger('onu_total')->default(0);
            $table->unsignedInteger('onu_online')->default(0);
            $table->unsignedInteger('onu_offline')->default(0);
            $table->unsignedInteger('onu_critical')->default(0);
            $table->unsignedInteger('onu_warning')->default(0);
            $table->decimal('avg_rx_dbm', 8, 3)->nullable();
            $table->decimal('min_rx_dbm', 8, 3)->nullable();
            $table->decimal('max_rx_dbm', 8, 3)->nullable();
            $table->decimal('fault_percent', 5, 2)->default(0);
            $table->timestamp('computed_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['olt_id', 'olt_port_id', 'computed_at']);
        });

        Schema::create('fiber_fault_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('olt_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('olt_port_id')->nullable()->constrained('olt_ports')->nullOnDelete();
            $table->string('fault_type', 48);
            $table->string('severity', 16)->default('critical');
            $table->unsignedInteger('affected_onu_count')->default(0);
            $table->unsignedInteger('affected_customer_count')->default(0);
            $table->text('description')->nullable();
            $table->json('affected_zones')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['olt_id', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiber_fault_logs');
        Schema::dropIfExists('pon_signal_stats');
        Schema::dropIfExists('signal_alerts');
        Schema::dropIfExists('onu_health_scores');
        Schema::dropIfExists('onu_signal_logs');
    }
};
