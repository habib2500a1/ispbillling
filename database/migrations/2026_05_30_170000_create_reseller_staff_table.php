<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_staff', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('login', 64);
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('password');
            $table->json('portal_permissions')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->unique(['tenant_id', 'login']);
            $table->index(['reseller_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_staff');
    }
};
