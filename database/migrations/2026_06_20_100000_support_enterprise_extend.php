<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_root_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('incident_number', 40);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('active');
            $table->foreignId('olt_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('pop_box_id')->nullable()->constrained('pop_boxes')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('primary_ticket_id')->nullable()->constrained('support_tickets')->nullOnDelete();
            $table->unsignedInteger('ticket_count')->default(0);
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'incident_number']);
            $table->index(['tenant_id', 'status', 'detected_at']);
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->foreignId('parent_ticket_id')->nullable()->after('customer_id')->constrained('support_tickets')->nullOnDelete();
            $table->foreignId('root_incident_id')->nullable()->after('parent_ticket_id')->constrained('support_root_incidents')->nullOnDelete();
            $table->foreignId('olt_device_id')->nullable()->after('assigned_to')->constrained('devices')->nullOnDelete();
            $table->foreignId('pop_box_id')->nullable()->after('olt_device_id')->constrained('pop_boxes')->nullOnDelete();
            $table->timestamp('first_response_due_at')->nullable()->after('sla_resolve_due_at');
            $table->timestamp('first_responded_at')->nullable()->after('first_response_due_at');
            $table->timestamp('first_response_breached_notified_at')->nullable()->after('first_responded_at');
            $table->timestamp('eta_at')->nullable()->after('first_response_breached_notified_at');
            $table->timestamp('merged_at')->nullable()->after('eta_at');
            $table->string('sla_profile', 32)->default('standard')->after('merged_at');
        });

        Schema::table('support_assignment_rules', function (Blueprint $table) {
            $table->foreignId('pop_box_id')->nullable()->after('area_id')->constrained('pop_boxes')->nullOnDelete();
            $table->string('skill_tag', 64)->nullable()->after('department');
            $table->boolean('vip_priority')->default(false)->after('skill_tag');
            $table->unsignedSmallInteger('max_open_tickets')->nullable()->after('vip_priority');
        });

        Schema::table('field_visits', function (Blueprint $table) {
            $table->string('closure_photo_path')->nullable()->after('report');
            $table->string('closure_photo_disk', 32)->default('public')->after('closure_photo_path');
        });

        if (Schema::hasTable('support_tickets')) {
            DB::table('support_tickets')->where('status', 'pending')->update(['status' => 'pending_customer']);
        }
    }

    public function down(): void
    {
        Schema::table('field_visits', function (Blueprint $table) {
            $table->dropColumn(['closure_photo_path', 'closure_photo_disk']);
        });

        Schema::table('support_assignment_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pop_box_id');
            $table->dropColumn(['skill_tag', 'vip_priority', 'max_open_tickets']);
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_ticket_id');
            $table->dropConstrainedForeignId('root_incident_id');
            $table->dropConstrainedForeignId('olt_device_id');
            $table->dropConstrainedForeignId('pop_box_id');
            $table->dropColumn([
                'first_response_due_at',
                'first_responded_at',
                'first_response_breached_notified_at',
                'eta_at',
                'merged_at',
                'sla_profile',
            ]);
        });

        Schema::dropIfExists('support_root_incidents');
    }
};
