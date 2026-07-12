<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_onus')) {
            return;
        }

        Schema::create('customer_onus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customers_info_id')->constrained('customers_infos')->cascadeOnDelete();
            $table->unsignedBigInteger('olt_id')->nullable();
            $table->string('olt_name')->nullable();
            $table->string('pon_port')->nullable();
            $table->string('mac_address')->nullable();
            $table->string('serial_number')->nullable();
            $table->decimal('rx_power_dbm', 8, 3)->nullable();
            $table->decimal('tx_power_dbm', 8, 3)->nullable();
            $table->string('oper_status')->nullable();
            $table->string('source')->default('manual');
            $table->string('external_id')->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['customers_info_id', 'mac_address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_onus');
    }
};
