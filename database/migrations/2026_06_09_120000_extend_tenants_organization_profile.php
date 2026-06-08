<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('organization_type')->default('single_isp')->after('slug');
            $table->string('domain')->nullable()->after('organization_type');
            $table->text('address')->nullable()->after('domain');
            $table->string('contact_phone')->nullable()->after('address');
            $table->string('contact_email')->nullable()->after('contact_phone');
            $table->string('logo_path')->nullable()->after('contact_email');
            $table->json('branding')->nullable()->after('logo_path');
            $table->json('settings')->nullable()->after('branding');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'organization_type',
                'domain',
                'address',
                'contact_phone',
                'contact_email',
                'logo_path',
                'branding',
                'settings',
            ]);
        });
    }
};
