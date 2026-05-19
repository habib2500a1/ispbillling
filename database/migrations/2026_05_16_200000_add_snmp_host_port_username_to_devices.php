<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('snmp_host', 255)->nullable()->after('management_ip');
            $table->unsignedSmallInteger('snmp_port')->default(161)->after('snmp_host');
            $table->string('snmp_username', 128)->nullable()->after('snmp_port');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['snmp_host', 'snmp_port', 'snmp_username']);
        });
    }
};
