<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers_addresses')) {
            Schema::table('customers_addresses', function (Blueprint $table) {
                try {
                    $table->dropForeign('customers_addresses_label_name_foreign');
                } catch (\Throwable) {
                }
            });
        }

        if (Schema::hasTable('address_fields') && ! Schema::hasColumn('address_fields', 'saas_operator_id')) {
            Schema::table('address_fields', function (Blueprint $table) {
                $table->unsignedBigInteger('saas_operator_id')->nullable()->after('id');
                $table->index('saas_operator_id');
            });
        }

        if (Schema::hasTable('address_fields')) {
            Schema::table('address_fields', function (Blueprint $table) {
                try {
                    $table->dropUnique('address_fields_label_unique');
                } catch (\Throwable) {
                }
            });
        }

        if (Schema::hasTable('sms_templates') && ! Schema::hasColumn('sms_templates', 'saas_operator_id')) {
            Schema::table('sms_templates', function (Blueprint $table) {
                $table->unsignedBigInteger('saas_operator_id')->nullable()->after('id');
                $table->index('saas_operator_id');
            });
        }

        if (Schema::hasTable('sms_templates')) {
            Schema::table('sms_templates', function (Blueprint $table) {
                try {
                    $table->dropUnique('sms_templates_template_name_unique');
                } catch (\Throwable) {
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('address_fields') && Schema::hasColumn('address_fields', 'saas_operator_id')) {
            Schema::table('address_fields', function (Blueprint $table) {
                $table->dropIndex(['saas_operator_id']);
                $table->dropColumn('saas_operator_id');
            });
        }

        if (Schema::hasTable('sms_templates') && Schema::hasColumn('sms_templates', 'saas_operator_id')) {
            Schema::table('sms_templates', function (Blueprint $table) {
                $table->dropIndex(['saas_operator_id']);
                $table->dropColumn('saas_operator_id');
            });
        }
    }
};
