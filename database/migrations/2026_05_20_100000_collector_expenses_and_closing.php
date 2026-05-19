<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collector_expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('collector_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('collector_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('collector_expense_categories')->nullOnDelete();
            $table->string('expense_number', 32);
            $table->decimal('amount', 14, 2);
            $table->string('status', 16)->default('pending');
            $table->date('expense_date');
            $table->text('description')->nullable();
            $table->string('proof_path')->nullable();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'expense_number']);
            $table->index(['tenant_id', 'collector_id', 'status']);
            $table->index(['collector_id', 'expense_date']);
        });

        Schema::create('collector_daily_closings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('collector_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->date('closing_date');
            $table->decimal('collected_total', 14, 2)->default(0);
            $table->decimal('deposited_total', 14, 2)->default(0);
            $table->decimal('expense_total', 14, 2)->default(0);
            $table->decimal('declared_cash_in_hand', 14, 2)->default(0);
            $table->decimal('computed_due', 14, 2)->default(0);
            $table->decimal('cash_variance', 14, 2)->default(0);
            $table->string('status', 16)->default('submitted');
            $table->text('notes')->nullable();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['collector_id', 'closing_date']);
            $table->index(['tenant_id', 'closing_date']);
        });

        Schema::create('collector_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('collector_id')->constrained('users')->restrictOnDelete();
            $table->string('direction', 8);
            $table->decimal('amount', 14, 2);
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['collector_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_adjustments');
        Schema::dropIfExists('collector_daily_closings');
        Schema::dropIfExists('collector_expenses');
        Schema::dropIfExists('collector_expense_categories');
    }
};
