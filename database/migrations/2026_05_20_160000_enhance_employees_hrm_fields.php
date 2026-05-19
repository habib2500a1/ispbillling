<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->string('department', 80)->nullable()->after('designation');
            $table->date('join_date')->nullable()->after('department');
            $table->decimal('wallet_balance', 12, 2)->default(0)->after('base_salary');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['department', 'join_date', 'wallet_balance']);
        });
    }
};
