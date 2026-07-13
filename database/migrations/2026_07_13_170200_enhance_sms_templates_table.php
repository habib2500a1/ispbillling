<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('sms_templates', 'display_name')) {
                $table->string('display_name')->nullable()->after('template_name');
            }
            if (! Schema::hasColumn('sms_templates', 'event_key')) {
                $table->string('event_key', 64)->nullable()->after('display_name');
            }
            if (! Schema::hasColumn('sms_templates', 'placeholders')) {
                $table->json('placeholders')->nullable()->after('event_key');
            }
            if (! Schema::hasColumn('sms_templates', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sms_templates', function (Blueprint $table) {
            foreach (['display_name', 'event_key', 'placeholders', 'sort_order'] as $column) {
                if (Schema::hasColumn('sms_templates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
