<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_ppp_online')->default(false)->after('import_source');
            $table->timestamp('ppp_last_seen_at')->nullable()->after('is_ppp_online');

            $table->index(['tenant_id', 'is_ppp_online']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'is_ppp_online']);
            $table->dropColumn(['is_ppp_online', 'ppp_last_seen_at']);
        });
    }
};
