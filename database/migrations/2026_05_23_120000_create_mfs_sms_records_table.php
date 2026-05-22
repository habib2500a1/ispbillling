<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfs_sms_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('device_name', 120)->nullable();
            $table->string('gateway', 32)->index();
            $table->string('sender_type', 32)->default('personal');
            $table->string('sender_phone', 20)->nullable();
            $table->string('merchant_phone', 20)->nullable();
            $table->string('transaction_id', 64)->index();
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2)->nullable();
            $table->string('status', 32)->default('awaiting_review')->index();
            $table->unsignedBigInteger('matched_pending_id')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->text('raw_message')->nullable();
            $table->timestamp('sms_received_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'gateway', 'transaction_id'], 'mfs_sms_trx_unique');
            $table->index(['tenant_id', 'status', 'gateway']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfs_sms_records');
    }
};
