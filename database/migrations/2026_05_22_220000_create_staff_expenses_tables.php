<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('expense_source', 16)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('staff_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('expense_number', 32);
            $table->string('expense_source', 16);
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('staff_expense_categories')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 24)->default('cash');
            $table->string('status', 16)->default('pending');
            $table->date('expense_date');
            $table->text('description')->nullable();
            $table->string('proof_path')->nullable();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'expense_number']);
            $table->index(['tenant_id', 'status', 'expense_date']);
            $table->index(['tenant_id', 'expense_source']);
            $table->index(['submitted_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_expenses');
        Schema::dropIfExists('staff_expense_categories');
    }
};
