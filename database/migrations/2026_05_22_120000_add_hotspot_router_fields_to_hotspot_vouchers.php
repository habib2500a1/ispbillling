<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hotspot_vouchers') || Schema::hasColumn('hotspot_vouchers', 'mikrotik_server_id')) {
            return;
        }

        Schema::table('hotspot_vouchers', function (Blueprint $table): void {
            $table->foreignId('mikrotik_server_id')->nullable()->after('package_id')->constrained()->nullOnDelete();
            $table->string('hotspot_username', 64)->nullable()->after('mikrotik_server_id');
            $table->string('hotspot_password', 64)->nullable()->after('hotspot_username');
            $table->timestamp('provisioned_at')->nullable()->after('used_at');
            $table->text('provision_error')->nullable()->after('provisioned_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hotspot_vouchers') || ! Schema::hasColumn('hotspot_vouchers', 'mikrotik_server_id')) {
            return;
        }

        Schema::table('hotspot_vouchers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('mikrotik_server_id');
            $table->dropColumn(['hotspot_username', 'hotspot_password', 'provisioned_at', 'provision_error']);
        });
    }
};
