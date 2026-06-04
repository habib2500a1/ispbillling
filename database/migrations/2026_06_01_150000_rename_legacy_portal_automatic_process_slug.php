<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('automatic_processes')) {
            return;
        }

        DB::table('automatic_processes')
            ->whereIn('slug', ['legacyportal-onu-sync', 'ispdigital-onu-sync'])
            ->update([
                'slug' => 'legacy-portal-onu-sync',
                'artisan_command' => 'isp:legacy-portal-onu-sync',
            ]);
    }

    public function down(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('automatic_processes')) {
            return;
        }

        DB::table('automatic_processes')
            ->where('slug', 'legacy-portal-onu-sync')
            ->update([
                'slug' => 'legacyportal-onu-sync',
                'artisan_command' => 'isp:legacyportal-onu-sync',
            ]);
    }
};
