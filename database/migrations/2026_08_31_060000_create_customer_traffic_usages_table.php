<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_traffic_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('saas_operator_id')->nullable()->index();
            $table->unsignedBigInteger('ppp_secret_id')->nullable()->unique();
            $table->string('username')->nullable()->index();
            $table->string('router_name')->nullable()->index();
            $table->string('customer_unique_id')->nullable()->index();
            $table->unsignedBigInteger('session_rx_bytes')->default(0);
            $table->unsignedBigInteger('session_tx_bytes')->default(0);
            $table->timestamp('session_started_at')->nullable();
            $table->unsignedBigInteger('last_session_rx_bytes')->default(0);
            $table->unsignedBigInteger('last_session_tx_bytes')->default(0);
            $table->string('day_key', 10)->nullable()->index();
            $table->unsignedBigInteger('day_rx_bytes')->default(0);
            $table->unsignedBigInteger('day_tx_bytes')->default(0);
            $table->string('month_key', 7)->nullable()->index();
            $table->unsignedBigInteger('month_rx_bytes')->default(0);
            $table->unsignedBigInteger('month_tx_bytes')->default(0);
            $table->unsignedBigInteger('prev_rx_bytes')->default(0);
            $table->unsignedBigInteger('prev_tx_bytes')->default(0);
            $table->boolean('online')->default(false);
            $table->timestamp('polled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_traffic_usages');
    }
};
