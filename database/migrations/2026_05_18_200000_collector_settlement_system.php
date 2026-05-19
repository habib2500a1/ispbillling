<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collector_collections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('collector_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->decimal('amount', 14, 2);
            $table->decimal('amount_settled', 14, 2)->default(0);
            $table->string('payment_method', 32)->default('cash');
            $table->string('status', 16)->default('open');
            $table->timestamp('collected_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('payment_id');
            $table->index(['tenant_id', 'collector_id', 'status']);
            $table->index(['collector_id', 'collected_at']);
        });

        Schema::create('collector_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('settlement_number', 32);
            $table->foreignId('collector_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 32)->default('cash');
            $table->string('reference', 64)->nullable();
            $table->text('notes')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('status', 16)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->unsignedBigInteger('cashbook_entry_id')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'settlement_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['collector_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_settlements');
        Schema::dropIfExists('collector_collections');
    }
};
