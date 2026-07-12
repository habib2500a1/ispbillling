<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_attendance_logs')) {
            Schema::create('hr_attendance_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->date('work_date')->index();
                $table->timestamp('clock_in_at')->nullable();
                $table->timestamp('clock_out_at')->nullable();
                $table->string('status', 24)->default('present'); // present, late, half_day, absent_marked
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('recorded_by')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'work_date']);
            });
        }

        if (! Schema::hasTable('hr_leave_requests')) {
            Schema::create('hr_leave_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->date('from_date');
                $table->date('to_date');
                $table->string('leave_type', 32)->default('casual'); // casual, sick, unpaid, other
                $table->string('status', 24)->default('pending'); // pending, approved, rejected, cancelled
                $table->text('reason')->nullable();
                $table->text('admin_note')->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_requests');
        Schema::dropIfExists('hr_attendance_logs');
    }
};
