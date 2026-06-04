<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->string('expense_type', 16)->default('vendor')->after('vendor_id');
            $table->string('expense_category', 64)->nullable()->after('expense_type');
            $table->string('payee_name', 255)->nullable()->after('expense_category');
        });

        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
        });

        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('vendor_id')->nullable()->change();
            $table->foreign('vendor_id')->references('id')->on('vendors')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
        });

        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('vendor_id')->nullable(false)->change();
            $table->foreign('vendor_id')->references('id')->on('vendors')->restrictOnDelete();
        });

        Schema::table('vendor_payments', function (Blueprint $table) {
            $table->dropColumn(['expense_type', 'expense_category', 'payee_name']);
        });
    }
};
