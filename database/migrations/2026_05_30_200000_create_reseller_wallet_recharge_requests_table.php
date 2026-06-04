<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_wallet_recharge_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('reseller_id')->constrained('resellers')->cascadeOnDelete();
            $table->string('request_number', 32);
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 32);
            $table->string('reference')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('gateway', 32)->nullable();
            $table->string('gateway_transaction_id')->nullable();
            $table->string('checkout_order_id')->nullable();
            $table->foreignId('balance_transfer_id')->nullable()->constrained('reseller_balance_transfers')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('submitted_by_staff_id')->nullable()->constrained('reseller_staff')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'request_number']);
            $table->index(['reseller_id', 'status', 'created_at']);
            $table->index(['checkout_order_id']);
            $table->index(['gateway_transaction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_wallet_recharge_requests');
    }
};
