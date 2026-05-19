<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('customers', 'meta')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('customers', 'meta')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
