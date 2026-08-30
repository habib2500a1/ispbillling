<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_operators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('company');
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('plan')->default('standard');
            $table->string('status')->default('active');
            $table->boolean('can_resell')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_operators');
    }
};
