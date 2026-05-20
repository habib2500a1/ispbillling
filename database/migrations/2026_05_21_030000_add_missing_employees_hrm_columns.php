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
            if (! Schema::hasColumn('employees', 'department')) {
                $table->string('department', 80)->nullable()->after('designation');
            }
            if (! Schema::hasColumn('employees', 'join_date')) {
                $table->date('join_date')->nullable()->after(
                    Schema::hasColumn('employees', 'department') ? 'department' : 'designation',
                );
            }
            if (! Schema::hasColumn('employees', 'wallet_balance')) {
                $table->decimal('wallet_balance', 12, 2)->default(0)->after('base_salary');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employees')) {
            return;
        }

        Schema::table('employees', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('employees', 'department') ? 'department' : null,
                Schema::hasColumn('employees', 'join_date') ? 'join_date' : null,
                Schema::hasColumn('employees', 'wallet_balance') ? 'wallet_balance' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
