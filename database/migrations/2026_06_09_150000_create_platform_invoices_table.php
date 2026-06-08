<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('billing_period', 7);
            $table->string('plan_key', 64);
            $table->string('plan_name');
            $table->unsignedInteger('customer_count')->default(0);
            $table->unsignedInteger('max_customers')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('status', 32)->default('issued');
            $table->date('issue_date');
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'billing_period']);
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_invoices');
    }
};
