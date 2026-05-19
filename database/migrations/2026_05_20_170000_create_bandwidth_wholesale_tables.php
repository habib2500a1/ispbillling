<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bandwidth_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('client_code', 32)->nullable();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->decimal('profile_total', 14, 2)->default(0)->comment('Monthly bandwidth profile amount BDT');
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('bandwidth_client_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bandwidth_client_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number', 40)->nullable();
            $table->unsignedSmallInteger('period_month')->nullable();
            $table->unsignedSmallInteger('period_year')->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->string('status', 20)->default('due');
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['bandwidth_client_id', 'status']);
        });

        Schema::create('bandwidth_client_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bandwidth_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bandwidth_client_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamp('paid_at');
            $table->string('method', 40)->default('cash');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['bandwidth_client_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bandwidth_client_payments');
        Schema::dropIfExists('bandwidth_client_invoices');
        Schema::dropIfExists('bandwidth_clients');
    }
};
