<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_outage_notices', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->string('severity', 32)->default('info'); // info|warning|critical
            $table->string('scope', 64)->default('network'); // network|olt|router|area
            $table->string('area_label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_outage_notices');
    }
};
