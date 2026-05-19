<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onu_signal_logs', function (Blueprint $table) {
            $table->decimal('raw_rx_power_dbm', 8, 3)->nullable()->after('tx_power_dbm');
            $table->decimal('raw_tx_power_dbm', 8, 3)->nullable()->after('raw_rx_power_dbm');
            $table->decimal('temperature_c', 6, 2)->nullable()->after('raw_tx_power_dbm');
            $table->decimal('voltage_v', 6, 3)->nullable()->after('temperature_c');
            $table->boolean('is_spike')->default(false)->after('voltage_v');
            $table->string('poll_source', 32)->nullable()->after('is_spike');
        });

        Schema::table('onu_health_scores', function (Blueprint $table) {
            $table->decimal('smoothed_rx_dbm', 8, 3)->nullable()->after('rx_trend_dbm');
            $table->decimal('smoothed_tx_dbm', 8, 3)->nullable()->after('smoothed_rx_dbm');
            $table->decimal('rx_stddev_db', 8, 3)->nullable()->after('smoothed_tx_dbm');
            $table->unsignedTinyInteger('fiber_health_score')->nullable()->after('rx_stddev_db');
        });
    }

    public function down(): void
    {
        Schema::table('onu_health_scores', function (Blueprint $table) {
            $table->dropColumn(['smoothed_rx_dbm', 'smoothed_tx_dbm', 'rx_stddev_db', 'fiber_health_score']);
        });

        Schema::table('onu_signal_logs', function (Blueprint $table) {
            $table->dropColumn([
                'raw_rx_power_dbm',
                'raw_tx_power_dbm',
                'temperature_c',
                'voltage_v',
                'is_spike',
                'poll_source',
            ]);
        });
    }
};
