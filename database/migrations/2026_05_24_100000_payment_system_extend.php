<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_type', 32)->default('payment')->after('invoice_id');
            $table->string('receipt_number')->nullable()->after('payment_type');
            $table->foreignId('parent_payment_id')->nullable()->after('receipt_number')
                ->constrained('payments')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->after('parent_payment_id')
                ->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable()->after('reference');

            $table->unique(['tenant_id', 'receipt_number']);
            $table->index(['gateway', 'gateway_transaction_id']);
            $table->index(['payment_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['parent_payment_id']);
            $table->dropForeign(['recorded_by']);
            $table->dropIndex(['gateway', 'gateway_transaction_id']);
            $table->dropIndex(['payment_type', 'status']);
            $table->dropUnique(['tenant_id', 'receipt_number']);
            $table->dropColumn([
                'payment_type',
                'receipt_number',
                'parent_payment_id',
                'recorded_by',
                'notes',
            ]);
        });
    }
};
