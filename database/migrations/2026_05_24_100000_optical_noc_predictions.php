<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signal_predictions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('olt_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('scope', 32)->default('onu');
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->string('risk_level', 16)->default('normal');
            $table->string('prediction_type', 64);
            $table->text('summary');
            $table->json('factors')->nullable();
            $table->timestamp('predicted_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'risk_level', 'predicted_at']);
            $table->index(['device_id', 'prediction_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signal_predictions');
    }
};
