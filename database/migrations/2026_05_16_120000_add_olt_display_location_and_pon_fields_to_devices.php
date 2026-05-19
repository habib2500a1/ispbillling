<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('vendor');
            $table->string('location')->nullable()->after('display_name');
            $table->unsignedTinyInteger('card_no')->nullable()->after('onu_external_id');
            $table->unsignedTinyInteger('pon_no')->nullable()->after('card_no');
            $table->unsignedSmallInteger('onu_index')->nullable()->after('pon_no');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn([
                'display_name',
                'location',
                'card_no',
                'pon_no',
                'onu_index',
            ]);
        });
    }
};
