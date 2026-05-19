<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mikrotik_session_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('alert_type', 64);
            $table->string('severity', 16)->default('warning');
            $table->string('login', 128)->nullable();
            $table->text('message');
            $table->json('meta')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'resolved_at', 'alert_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mikrotik_session_alerts');
    }
};
