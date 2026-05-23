<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table): void {
            $table->boolean('auto_invoice_enabled')->default(true)->after('portal_permissions');
            $table->boolean('auto_suspend_enabled')->default(true)->after('auto_invoice_enabled');
            $table->text('two_factor_secret')->nullable()->after('portal_password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->json('portal_devices')->nullable()->after('portal_last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('resellers', function (Blueprint $table): void {
            $table->dropColumn([
                'auto_invoice_enabled',
                'auto_suspend_enabled',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'portal_devices',
            ]);
        });
    }
};
