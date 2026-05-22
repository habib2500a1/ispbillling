<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotspot_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('batch_name', 64)->nullable();
            $table->unsignedSmallInteger('duration_hours')->default(24);
            $table->unsignedInteger('data_limit_mb')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status', 16)->default('available');
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mikrotik_server_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hotspot_username', 64)->nullable();
            $table->string('hotspot_password', 64)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->text('provision_error')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('sales_leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('source', 32)->default('walk_in');
            $table->string('status', 32)->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('estimated_mrr', 12, 2)->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->foreignId('converted_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'phone']);
        });

        Schema::create('ip_pools', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('subnet', 64);
            $table->string('gateway', 45)->nullable();
            $table->string('dns_primary', 45)->nullable();
            $table->string('dns_secondary', 45)->nullable();
            $table->foreignId('mikrotik_server_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('ip_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ip_pool_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('free');
            $table->timestamp('assigned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['ip_pool_id', 'ip_address']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_allocations');
        Schema::dropIfExists('ip_pools');
        Schema::dropIfExists('sales_leads');
        Schema::dropIfExists('hotspot_vouchers');
    }
};
