<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automatic_process_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automatic_process_id')->constrained()->cascadeOnDelete();
            $table->string('triggered_by', 32)->default('scheduler');
            $table->unsignedSmallInteger('exit_code')->nullable();
            $table->string('status', 32);
            $table->text('output')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['automatic_process_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automatic_process_runs');
    }
};
