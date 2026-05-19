<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table): void {
            $table->string('client_id_prefix', 32)->nullable()->after('code');
            $table->string('portal_login', 64)->nullable()->after('email');
            $table->foreignId('primary_user_id')->nullable()->after('portal_login')->constrained('users')->nullOnDelete();
            $table->string('address')->nullable()->after('contact_person');
            $table->string('city', 64)->nullable()->after('address');
            $table->string('district', 64)->nullable()->after('city');
            $table->string('trade_license', 64)->nullable()->after('district');
            $table->string('nid_number', 32)->nullable()->after('trade_license');
        });
    }

    public function down(): void
    {
        Schema::table('resellers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('primary_user_id');
            $table->dropColumn([
                'client_id_prefix',
                'portal_login',
                'address',
                'city',
                'district',
                'trade_license',
                'nid_number',
            ]);
        });
    }
};
