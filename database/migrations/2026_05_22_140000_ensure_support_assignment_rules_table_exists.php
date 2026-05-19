<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-heal installs where support_ecosystem_extend did not run or failed before
 * creating support_assignment_rules (Filament /admin/support-assignment-rules 500).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('support_assignment_rules')) {
            return;
        }

        Schema::create('support_assignment_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('department', 40)->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'area_id', 'department']);
        });
    }

    public function down(): void
    {
        // Do not drop: table may pre-exist from 2026_05_20_120000_support_ecosystem_extend.
    }
};
