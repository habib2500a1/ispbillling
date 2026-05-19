<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_delivery_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('notification_log_id')->nullable();
            $table->string('gateway', 32)->default('khudebarta');
            $table->string('gateway_message_id', 64)->index();
            $table->string('recipient', 32)->nullable();
            $table->string('delivery_status', 64)->nullable();
            $table->string('status_text', 128)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'gateway_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_delivery_reports');
    }
};
