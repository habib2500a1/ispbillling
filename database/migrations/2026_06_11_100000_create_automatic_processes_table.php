<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automatic_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('artisan_command');
            $table->json('command_options')->nullable();
            $table->string('execute_at', 5)->default('00:00');
            $table->string('interval', 32)->default('daily');
            $table->boolean('enabled')->default(true);
            $table->string('when_config_key')->nullable();
            $table->unsignedSmallInteger('without_overlapping_minutes')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status', 32)->nullable();
            $table->text('last_output')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automatic_processes');
    }
};
