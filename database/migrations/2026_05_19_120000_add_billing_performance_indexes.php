<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->index(['tenant_id', 'status', 'paid_at'], 'payments_tenant_status_paid_idx');
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->index(['tenant_id', 'status', 'due_date'], 'invoices_tenant_status_due_idx');
                $table->index(['customer_id', 'status'], 'invoices_customer_status_idx');
            });
        }

        if (Schema::hasTable('devices')) {
            Schema::table('devices', function (Blueprint $table): void {
                $table->index(['customer_id', 'type'], 'devices_customer_type_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->dropIndex('payments_tenant_status_paid_idx');
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropIndex('invoices_tenant_status_due_idx');
                $table->dropIndex('invoices_customer_status_idx');
            });
        }

        if (Schema::hasTable('devices')) {
            Schema::table('devices', function (Blueprint $table): void {
                $table->dropIndex('devices_customer_type_idx');
            });
        }
    }
};
