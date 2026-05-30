<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table): void {
            $table->unsignedInteger('max_clients')->nullable()->after('wallet_balance');
            $table->unsignedInteger('max_active_clients')->nullable()->after('max_clients');
            $table->boolean('wallet_frozen')->default(false)->after('max_active_clients');
        });
    }

    public function down(): void
    {
        Schema::table('resellers', function (Blueprint $table): void {
            $table->dropColumn(['max_clients', 'max_active_clients', 'wallet_frozen']);
        });
    }
};
