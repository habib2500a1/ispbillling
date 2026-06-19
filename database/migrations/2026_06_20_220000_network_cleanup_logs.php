<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('network_cleanup_logs')) {
            return;
        }

        Schema::create('network_cleanup_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('workflow', 64);
            $table->json('actions')->nullable();
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->index(['tenant_id', 'processed_at']);
            $table->index(['customer_id', 'workflow']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_cleanup_logs');
    }
};
