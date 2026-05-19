<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fair_usage_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('daily_cap_gb')->nullable();
            $table->unsignedInteger('monthly_cap_gb')->nullable();
            $table->unsignedInteger('throttle_download_mbps')->nullable();
            $table->string('action_on_exceed', 32)->default('alert');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        if (Schema::hasTable('packages') && ! Schema::hasColumn('packages', 'fair_usage_policy_id')) {
            Schema::table('packages', function (Blueprint $table): void {
                $table->foreignId('fair_usage_policy_id')->nullable()->after('is_active')->constrained('fair_usage_policies')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('packages', 'fair_usage_policy_id')) {
            Schema::table('packages', fn (Blueprint $table) => $table->dropConstrainedForeignId('fair_usage_policy_id'));
        }

        Schema::dropIfExists('fair_usage_policies');
    }
};
