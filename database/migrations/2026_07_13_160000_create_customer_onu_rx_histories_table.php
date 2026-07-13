<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_onu_rx_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_onu_id')->constrained('customer_onus')->cascadeOnDelete();
            $table->decimal('rx_power_dbm', 8, 3)->nullable();
            $table->decimal('tx_power_dbm', 8, 3)->nullable();
            $table->string('source', 32)->default('ispbilling');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['customer_onu_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_onu_rx_histories');
    }
};
