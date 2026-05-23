<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->index(['tenant_id', 'type', 'onu_oper_status'], 'devices_tenant_type_onu_status_idx');
            $table->index(['tenant_id', 'type', 'rx_power_dbm'], 'devices_tenant_type_rx_idx');
            $table->index(['olt_id', 'type'], 'devices_olt_type_idx');
            $table->index(['tenant_id', 'type', 'last_polled_at'], 'devices_tenant_type_polled_idx');
        });

        if (Schema::hasTable('onu_signal_logs')) {
            Schema::table('onu_signal_logs', function (Blueprint $table) {
                $table->index(['tenant_id', 'granularity', 'sampled_at'], 'onu_logs_tenant_granularity_idx');
            });
        }

        if (Schema::hasTable('signal_alerts')) {
            Schema::table('signal_alerts', function (Blueprint $table) {
                $table->index(['device_id', 'status', 'alert_type'], 'signal_alerts_device_status_type_idx');
            });
        }

        if (Schema::hasTable('olt_health_logs')) {
            Schema::table('olt_health_logs', function (Blueprint $table) {
                $table->index(['sampled_at'], 'olt_health_logs_sampled_idx');
            });
        }

        if (Schema::hasTable('signal_predictions')) {
            Schema::table('signal_predictions', function (Blueprint $table) {
                $table->index(['expires_at'], 'signal_predictions_expires_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('signal_predictions')) {
            Schema::table('signal_predictions', function (Blueprint $table) {
                $table->dropIndex('signal_predictions_expires_idx');
            });
        }

        if (Schema::hasTable('olt_health_logs')) {
            Schema::table('olt_health_logs', function (Blueprint $table) {
                $table->dropIndex('olt_health_logs_sampled_idx');
            });
        }

        if (Schema::hasTable('signal_alerts')) {
            Schema::table('signal_alerts', function (Blueprint $table) {
                $table->dropIndex('signal_alerts_device_status_type_idx');
            });
        }

        if (Schema::hasTable('onu_signal_logs')) {
            Schema::table('onu_signal_logs', function (Blueprint $table) {
                $table->dropIndex('onu_logs_tenant_granularity_idx');
            });
        }

        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex('devices_tenant_type_onu_status_idx');
            $table->dropIndex('devices_tenant_type_rx_idx');
            $table->dropIndex('devices_olt_type_idx');
            $table->dropIndex('devices_tenant_type_polled_idx');
        });
    }
};
