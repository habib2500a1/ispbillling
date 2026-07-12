<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE router_lists MODIFY ip_address TEXT NOT NULL');
        DB::statement('ALTER TABLE router_lists MODIFY username TEXT NOT NULL');
        DB::statement('ALTER TABLE router_lists MODIFY password TEXT NOT NULL');
        DB::statement('ALTER TABLE router_lists MODIFY ssh_port TEXT NULL');
        DB::statement('ALTER TABLE router_lists MODIFY api_port TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE router_lists MODIFY ip_address VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE router_lists MODIFY username VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE router_lists MODIFY password VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE router_lists MODIFY ssh_port VARCHAR(255) NULL');
        DB::statement('ALTER TABLE router_lists MODIFY api_port VARCHAR(255) NULL');
    }
};
