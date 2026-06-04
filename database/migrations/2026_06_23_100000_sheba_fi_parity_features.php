<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotional_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('discount_type', 32)->default('percent');
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->json('package_ids')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->text('terms')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('call_center_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('websip_enabled')->default(false);
            $table->string('sip_server')->nullable();
            $table->string('wss_uri')->nullable();
            $table->string('sip_domain')->nullable();
            $table->string('default_extension')->nullable();
            $table->string('outbound_caller_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direction', 16)->default('outbound');
            $table->string('phone', 32)->nullable();
            $table->string('staff_extension', 32)->nullable();
            $table->string('status', 32)->default('completed');
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->text('remarks')->nullable();
            $table->string('recording_url')->nullable();
            $table->string('external_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'started_at']);
            $table->index(['customer_id', 'started_at']);
            $table->unique(['tenant_id', 'external_id']);
        });

        Schema::create('call_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone', 32)->nullable();
            $table->string('subject')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('scheduled_at');
            $table->string('status', 32)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'scheduled_at']);
        });

        Schema::create('voice_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('language', 16)->default('bn');
            $table->string('type', 32)->default('announcement');
            $table->text('transcript')->nullable();
            $table->string('audio_path')->nullable();
            $table->string('audio_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('voice_sms_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voice_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('status', 32)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedInteger('targets_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('target_filters')->nullable();
            $table->timestamps();
        });

        Schema::create('store_device_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('issued');
            $table->string('condition_out', 8)->default('G');
            $table->string('condition_in', 8)->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('due_return_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('issue_notes')->nullable();
            $table->text('return_notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_device_loans');
        Schema::dropIfExists('voice_sms_campaigns');
        Schema::dropIfExists('voice_templates');
        Schema::dropIfExists('call_follow_ups');
        Schema::dropIfExists('call_logs');
        Schema::dropIfExists('call_center_settings');
        Schema::dropIfExists('promotional_offers');
    }
};
