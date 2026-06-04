<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table): void {
            if (! Schema::hasColumn('resellers', 'meta')) {
                $table->json('meta')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('resellers', function (Blueprint $table): void {
            if (Schema::hasColumn('resellers', 'meta')) {
                $table->dropColumn('meta');
            }
        });
    }
};
