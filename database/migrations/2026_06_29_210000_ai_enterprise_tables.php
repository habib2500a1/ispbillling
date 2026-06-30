<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_interaction_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('channel', 32)->index();
            $table->string('actor_type', 32)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('locale', 8)->default('en');
            $table->text('query');
            $table->text('reply')->nullable();
            $table->string('tool', 64)->nullable()->index();
            $table->string('domain', 32)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->boolean('llm_used')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('ai_action_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('action_type', 64)->index();
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedBigInteger('requested_by')->nullable()->index();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->text('summary');
            $table->json('payload');
            $table->json('preview')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('ai_knowledge_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('category', 64)->default('sop')->index();
            $table->string('title');
            $table->text('content');
            $table->string('locale', 8)->default('bn');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_knowledge_documents');
        Schema::dropIfExists('ai_action_requests');
        Schema::dropIfExists('ai_interaction_logs');
    }
};
