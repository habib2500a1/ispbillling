<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_templates', function (Blueprint $table) {
            $table->boolean('voice_enabled')->default(false)->after('is_enabled');
            $table->foreignId('voice_template_id')
                ->nullable()
                ->after('voice_enabled')
                ->constrained('voice_templates')
                ->nullOnDelete();
        });

        Schema::table('voice_sms_campaigns', function (Blueprint $table) {
            $table->boolean('send_sms')->default(true)->after('name');
            $table->boolean('send_voice')->default(false)->after('send_sms');
            $table->unsignedInteger('voice_sent_count')->default(0)->after('failed_count');
            $table->unsignedInteger('voice_failed_count')->default(0)->after('voice_sent_count');
        });
    }

    public function down(): void
    {
        Schema::table('voice_sms_campaigns', function (Blueprint $table) {
            $table->dropColumn(['send_sms', 'send_voice', 'voice_sent_count', 'voice_failed_count']);
        });

        Schema::table('sms_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voice_template_id');
            $table->dropColumn('voice_enabled');
        });
    }
};
