<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wan_bandwidth_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mikrotik_server_id')->constrained()->cascadeOnDelete();
            $table->string('interface_name', 64);
            $table->unsignedBigInteger('bytes_in')->default(0);
            $table->unsignedBigInteger('bytes_out')->default(0);
            $table->unsignedBigInteger('rate_in_bps')->nullable();
            $table->unsignedBigInteger('rate_out_bps')->nullable();
            $table->timestamp('sampled_at');
            $table->timestamps();

            $table->index(['tenant_id', 'sampled_at']);
            $table->index(['mikrotik_server_id', 'interface_name', 'sampled_at'], 'wan_samples_server_if_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wan_bandwidth_samples');
    }
};
