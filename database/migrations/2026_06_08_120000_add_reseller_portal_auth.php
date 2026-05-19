<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            $table->string('portal_password')->nullable()->after('email');
            $table->rememberToken()->after('portal_password');
            $table->timestamp('portal_last_login_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            $table->dropColumn(['portal_password', 'remember_token', 'portal_last_login_at']);
        });
    }
};
