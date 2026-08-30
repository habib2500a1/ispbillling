<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('customer_unique_id')->nullable()->index();
            $table->string('mobile')->nullable()->index();
            $table->text('body');
            $table->string('source', 40)->default('custom');
            $table->string('status', 20)->default('failed')->index();
            $table->string('error')->nullable();
            $table->string('sent_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_sms_logs');
    }
};
