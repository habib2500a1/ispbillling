<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('leave_type', 32);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason')->nullable();
            $table->string('status', 24)->default('approved');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::table('payroll_items', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_items', 'basic_salary')) {
                $table->decimal('basic_salary', 12, 2)->default(0)->after('employee_id');
            }
            if (! Schema::hasColumn('payroll_items', 'auto_deductions')) {
                $table->decimal('auto_deductions', 12, 2)->default(0)->after('basic_salary');
            }
            if (! Schema::hasColumn('payroll_items', 'allowances')) {
                $table->decimal('allowances', 12, 2)->default(0)->after('auto_deductions');
            }
            if (! Schema::hasColumn('payroll_items', 'manual_deduction')) {
                $table->decimal('manual_deduction', 12, 2)->default(0)->after('allowances');
            }
            if (! Schema::hasColumn('payroll_items', 'bonus_amount')) {
                $table->decimal('bonus_amount', 12, 2)->default(0)->after('manual_deduction');
            }
            if (! Schema::hasColumn('payroll_items', 'amount_due')) {
                $table->decimal('amount_due', 12, 2)->default(0)->after('net_salary');
            }
            if (! Schema::hasColumn('payroll_items', 'payment_status')) {
                $table->string('payment_status', 24)->default('pending')->after('amount_due');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            foreach (['basic_salary', 'auto_deductions', 'allowances', 'manual_deduction', 'bonus_amount', 'amount_due', 'payment_status'] as $col) {
                if (Schema::hasColumn('payroll_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('employee_leave_requests');
    }
};
