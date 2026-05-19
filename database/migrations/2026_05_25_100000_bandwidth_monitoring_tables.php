<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bandwidth_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mikrotik_server_id')->nullable()->constrained('mikrotik_servers')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('session_key', 128);
            $table->string('username', 128);
            $table->unsignedBigInteger('bytes_in')->default(0);
            $table->unsignedBigInteger('bytes_out')->default(0);
            $table->unsignedBigInteger('rate_in_bps')->nullable();
            $table->unsignedBigInteger('rate_out_bps')->nullable();
            $table->string('framed_ip', 45)->nullable();
            $table->string('caller_id', 64)->nullable();
            $table->timestamp('sampled_at');
            $table->timestamps();

            $table->index(['customer_id', 'sampled_at']);
            $table->index(['session_key', 'sampled_at']);
        });

        Schema::create('ppp_session_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mikrotik_server_id')->nullable()->constrained('mikrotik_servers')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('session_key', 128)->unique();
            $table->string('username', 128);
            $table->string('framed_ip', 45)->nullable();
            $table->string('caller_id', 64)->nullable();
            $table->unsignedBigInteger('bytes_in')->default(0);
            $table->unsignedBigInteger('bytes_out')->default(0);
            $table->unsignedBigInteger('peak_rate_in_bps')->nullable();
            $table->unsignedBigInteger('peak_rate_out_bps')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('status', 16)->default('active');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'started_at']);
            $table->index(['status', 'tenant_id']);
        });

        Schema::create('bandwidth_usage_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('usage_date');
            $table->unsignedBigInteger('bytes_in')->default(0);
            $table->unsignedBigInteger('bytes_out')->default(0);
            $table->unsignedBigInteger('peak_rate_in_bps')->default(0);
            $table->unsignedBigInteger('peak_rate_out_bps')->default(0);
            $table->unsignedInteger('online_seconds')->default(0);
            $table->unsignedSmallInteger('session_count')->default(0);
            $table->timestamps();

            $table->unique(['customer_id', 'usage_date']);
        });

        Schema::create('bandwidth_abuse_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->default(1)->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('alert_type', 48);
            $table->string('severity', 16)->default('warning');
            $table->text('message');
            $table->json('meta')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'resolved_at']);
            $table->index(['customer_id', 'alert_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bandwidth_abuse_alerts');
        Schema::dropIfExists('bandwidth_usage_daily');
        Schema::dropIfExists('ppp_session_logs');
        Schema::dropIfExists('bandwidth_samples');
    }
};
