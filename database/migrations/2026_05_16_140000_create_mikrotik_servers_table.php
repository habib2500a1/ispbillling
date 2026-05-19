<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mikrotik_servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('host');
            $table->unsignedSmallInteger('api_port')->default(8728);
            $table->boolean('use_ssl')->default(false);
            $table->boolean('legacy_login')->default(false);
            $table->string('api_username');
            $table->text('api_password');
            $table->text('default_ppp_password')->nullable();
            $table->string('ppp_profile_default')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->string('last_api_status', 16)->default('unknown');
            $table->text('last_error')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::table('mikrotik_servers', function (Blueprint $table) {
            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mikrotik_servers');
    }
};
