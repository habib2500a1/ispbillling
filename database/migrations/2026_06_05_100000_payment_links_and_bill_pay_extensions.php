<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->string('purpose', 32)->default('invoice');
            $table->string('source_event', 64)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('sms_sent_at')->nullable();
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamp('first_clicked_at')->nullable();
            $table->foreignId('converted_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_links');
    }
};
