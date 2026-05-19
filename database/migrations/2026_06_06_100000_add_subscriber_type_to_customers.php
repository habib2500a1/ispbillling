<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('subscriber_type', 32)->default('standard')->after('status');
            $table->boolean('auto_suspend_override')->nullable()->after('subscriber_type');
            $table->index(['tenant_id', 'subscriber_type']);
            $table->index(['tenant_id', 'status', 'subscriber_type']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'subscriber_type']);
            $table->dropIndex(['tenant_id', 'status', 'subscriber_type']);
            $table->dropColumn(['subscriber_type', 'auto_suspend_override']);
        });
    }
};
