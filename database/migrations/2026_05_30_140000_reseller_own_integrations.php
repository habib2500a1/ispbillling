<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table): void {
            if (! Schema::hasColumn('resellers', 'own_integrations_enabled')) {
                $table->boolean('own_integrations_enabled')->default(false)->after('white_label_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('resellers', function (Blueprint $table): void {
            if (Schema::hasColumn('resellers', 'own_integrations_enabled')) {
                $table->dropColumn('own_integrations_enabled');
            }
        });
    }
};
