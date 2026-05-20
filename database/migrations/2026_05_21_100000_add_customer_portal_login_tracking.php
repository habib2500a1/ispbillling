<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'portal_last_login_at')) {
                $table->timestamp('portal_last_login_at')->nullable()->after('portal_password');
            }
            if (! Schema::hasColumn('customers', 'portal_last_logout_at')) {
                $table->timestamp('portal_last_logout_at')->nullable()->after('portal_last_login_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (Schema::hasColumn('customers', 'portal_last_logout_at')) {
                $table->dropColumn('portal_last_logout_at');
            }
            if (Schema::hasColumn('customers', 'portal_last_login_at')) {
                $table->dropColumn('portal_last_login_at');
            }
        });
    }
};
