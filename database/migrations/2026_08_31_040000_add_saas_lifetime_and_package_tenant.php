<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('saas_plans') && ! Schema::hasColumn('saas_plans', 'is_lifetime')) {
            Schema::table('saas_plans', function (Blueprint $table) {
                $table->boolean('is_lifetime')->default(false)->after('is_active');
            });
        }

        if (Schema::hasTable('package_lists') && ! Schema::hasColumn('package_lists', 'saas_operator_id')) {
            Schema::table('package_lists', function (Blueprint $table) {
                $table->unsignedBigInteger('saas_operator_id')->nullable()->after('id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('saas_plans') && Schema::hasColumn('saas_plans', 'is_lifetime')) {
            Schema::table('saas_plans', function (Blueprint $table) {
                $table->dropColumn('is_lifetime');
            });
        }

        if (Schema::hasTable('package_lists') && Schema::hasColumn('package_lists', 'saas_operator_id')) {
            Schema::table('package_lists', function (Blueprint $table) {
                $table->dropColumn('saas_operator_id');
            });
        }
    }
};
