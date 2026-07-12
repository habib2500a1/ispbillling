<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers_infos', function (Blueprint $table) {
            if (! Schema::hasColumn('customers_infos', 'portal_access_token_hash')) {
                $table->string('portal_access_token_hash')->nullable()->after('connection_date');
            }
            if (! Schema::hasColumn('customers_infos', 'portal_access_token_at')) {
                $table->timestamp('portal_access_token_at')->nullable()->after('portal_access_token_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers_infos', function (Blueprint $table) {
            if (Schema::hasColumn('customers_infos', 'portal_access_token_at')) {
                $table->dropColumn('portal_access_token_at');
            }
            if (Schema::hasColumn('customers_infos', 'portal_access_token_hash')) {
                $table->dropColumn('portal_access_token_hash');
            }
        });
    }
};
