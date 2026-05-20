<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('automatic_processes')) {
            return;
        }

        DB::table('automatic_processes')
            ->where('slug', 'mikrotik-poll-status')
            ->update(['when_config_key' => 'mikrotik.poll_enabled']);

        DB::table('automatic_processes')
            ->where('slug', 'mikrotik-fetch-details')
            ->update(['when_config_key' => 'mikrotik.fetch_details_poll_enabled']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('automatic_processes')) {
            return;
        }

        DB::table('automatic_processes')
            ->whereIn('slug', ['mikrotik-poll-status', 'mikrotik-fetch-details'])
            ->update(['when_config_key' => null]);
    }
};
