<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_wallet_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saas_operator_id')->nullable()->constrained('saas_operators')->nullOnDelete();
            $table->foreignId('reseller_id')->constrained('resellers')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 60);
            $table->string('payment_reference')->nullable();
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('admin_note')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('reseller_wallet_transactions')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['reseller_id', 'status', 'created_at']);
            $table->index(['saas_operator_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_wallet_approvals');
    }
};
