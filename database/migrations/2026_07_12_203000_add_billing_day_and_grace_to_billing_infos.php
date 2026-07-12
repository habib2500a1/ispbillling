<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_infos', function (Blueprint $table) {
            if (! Schema::hasColumn('billing_infos', 'billing_day')) {
                $table->unsignedTinyInteger('billing_day')->nullable()->after('billing_type');
            }
            if (! Schema::hasColumn('billing_infos', 'grace_period_days')) {
                $table->unsignedSmallInteger('grace_period_days')->default(0)->after('billing_day');
            }
        });
    }

    public function down(): void
    {
        Schema::table('billing_infos', function (Blueprint $table) {
            if (Schema::hasColumn('billing_infos', 'grace_period_days')) {
                $table->dropColumn('grace_period_days');
            }
            if (Schema::hasColumn('billing_infos', 'billing_day')) {
                $table->dropColumn('billing_day');
            }
        });
    }
};
