<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table): void {
            $table->string('billing_settlement_mode', 32)->default('postpaid_due')->after('commission_mode');
            $table->decimal('admin_receivable_due', 14, 2)->default(0)->after('bonus_wallet_balance');
            $table->decimal('margin_accrued_total', 14, 2)->default(0)->after('admin_receivable_due');
            $table->unsignedSmallInteger('due_grace_period_days')->default(15)->after('low_balance_threshold');
            $table->string('reseller_suspend_policy', 32)->default('credit_breach')->after('auto_suspend_on_low_balance');
            $table->string('customer_billing_policy', 32)->default('reseller_controlled')->after('reseller_suspend_policy');
            $table->boolean('allow_overdue_customers_active')->default(true)->after('customer_billing_policy');
            $table->boolean('suspend_reseller_customers_on_breach')->default(false)->after('allow_overdue_customers_active');
            $table->decimal('risk_score', 5, 2)->default(0)->after('suspend_reseller_customers_on_breach');
            $table->timestamp('billing_policy_evaluated_at')->nullable()->after('risk_score');
        });

        Schema::create('reseller_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entry_type', 48);
            $table->string('direction', 8);
            $table->decimal('amount', 14, 2);
            $table->decimal('admin_receivable_after', 14, 2)->default(0);
            $table->decimal('retail_amount', 14, 2)->default(0);
            $table->decimal('wholesale_amount', 14, 2)->default(0);
            $table->decimal('margin_amount', 14, 2)->default(0);
            $table->string('reference', 96)->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['reseller_id', 'created_at']);
            $table->index(['reseller_id', 'entry_type']);
            $table->index(['invoice_id']);
            $table->unique(['reseller_id', 'entry_type', 'reference']);
        });

        Schema::create('reseller_monthly_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->decimal('opening_admin_due', 14, 2)->default(0);
            $table->decimal('accruals', 14, 2)->default(0);
            $table->decimal('collections_applied', 14, 2)->default(0);
            $table->decimal('settlements', 14, 2)->default(0);
            $table->decimal('closing_admin_due', 14, 2)->default(0);
            $table->decimal('margin_total', 14, 2)->default(0);
            $table->string('status', 32)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['reseller_id', 'period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_monthly_statements');
        Schema::dropIfExists('reseller_ledger_entries');

        Schema::table('resellers', function (Blueprint $table): void {
            $table->dropColumn([
                'billing_settlement_mode',
                'admin_receivable_due',
                'margin_accrued_total',
                'due_grace_period_days',
                'reseller_suspend_policy',
                'customer_billing_policy',
                'allow_overdue_customers_active',
                'suspend_reseller_customers_on_breach',
                'risk_score',
                'billing_policy_evaluated_at',
            ]);
        });
    }
};
