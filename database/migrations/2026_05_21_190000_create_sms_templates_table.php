<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->string('key', 64);
            $table->string('name');
            $table->string('template_type', 32)->default('default');
            $table->string('event_key', 64)->nullable()->index();
            $table->text('body');
            $table->json('placeholders')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_templates');
    }
};
