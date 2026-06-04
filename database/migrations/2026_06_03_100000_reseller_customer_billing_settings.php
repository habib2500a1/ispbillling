<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table): void {
            $table->string('new_customer_charge_mode', 32)->default('prorated')->after('customer_billing_policy');
            $table->string('default_customer_billing_mode', 16)->default('prepaid')->after('new_customer_charge_mode');
            $table->unsignedSmallInteger('default_prepaid_grace_days')->default(5)->after('default_customer_billing_mode');
            $table->unsignedSmallInteger('default_postpaid_grace_days')->default(10)->after('default_prepaid_grace_days');
            $table->boolean('reseller_can_override_charge_mode')->default(false)->after('default_postpaid_grace_days');
        });
    }

    public function down(): void
    {
        Schema::table('resellers', function (Blueprint $table): void {
            $table->dropColumn([
                'new_customer_charge_mode',
                'default_customer_billing_mode',
                'default_prepaid_grace_days',
                'default_postpaid_grace_days',
                'reseller_can_override_charge_mode',
            ]);
        });
    }
};
