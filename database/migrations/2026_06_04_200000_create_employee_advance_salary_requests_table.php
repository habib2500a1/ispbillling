<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_advance_salary_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('request_date');
            $table->text('purpose')->nullable();
            $table->string('return_type', 32)->default('next_salary');
            $table->date('deduction_month');
            $table->string('status', 24)->default('approved');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deducted_at')->nullable();
            $table->foreignId('payroll_run_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['employee_id', 'deduction_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_advance_salary_requests');
    }
};
