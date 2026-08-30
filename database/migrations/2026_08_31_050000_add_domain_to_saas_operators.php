<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('saas_operators') && ! Schema::hasColumn('saas_operators', 'domain')) {
            Schema::table('saas_operators', function (Blueprint $table) {
                $table->string('domain')->nullable()->unique()->after('phone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('saas_operators') && Schema::hasColumn('saas_operators', 'domain')) {
            Schema::table('saas_operators', function (Blueprint $table) {
                $table->dropColumn('domain');
            });
        }
    }
};
