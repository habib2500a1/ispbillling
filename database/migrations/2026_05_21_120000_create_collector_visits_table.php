<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collector_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collector_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount_collected', 12, 2)->nullable();
            $table->string('payment_method', 32)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('accuracy_meters')->nullable();
            $table->string('location_text', 255)->nullable();
            $table->text('notes')->nullable();
            $table->json('device_meta')->nullable();
            $table->timestamp('visited_at');
            $table->timestamps();

            $table->index(['collector_id', 'visited_at']);
            $table->index(['customer_id', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_visits');
    }
};
