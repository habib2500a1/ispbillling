<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('call_desk_logs')) {
            return;
        }

        Schema::create('call_desk_logs', function (Blueprint $table) {
            $table->id();
            $table->string('customer_unique_id')->nullable()->index();
            $table->string('phone', 40)->nullable()->index();
            $table->unsignedBigInteger('staff_user_id')->nullable()->index();
            $table->string('direction', 16)->default('outbound');
            $table->string('outcome', 32)->default('answered');
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('support_ticket_id')->nullable()->index();
            $table->timestamp('called_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_desk_logs');
    }
};
