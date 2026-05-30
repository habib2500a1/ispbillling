<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiber_plant_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64)->nullable();
            $table->string('name');
            $table->string('type', 32);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('address')->nullable();
            $table->foreignId('pop_box_id')->nullable()->constrained('pop_boxes')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->unsignedSmallInteger('splitter_ratio')->nullable();
            $table->string('splitter_direction', 16)->nullable();
            $table->unsignedSmallInteger('bearing_deg')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'customer_id']);
        });

        Schema::create('fiber_plant_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_node_id')->constrained('fiber_plant_nodes')->cascadeOnDelete();
            $table->foreignId('to_node_id')->constrained('fiber_plant_nodes')->cascadeOnDelete();
            $table->string('cable_type', 32)->default('distribution');
            $table->unsignedTinyInteger('fiber_count')->default(2);
            $table->string('cable_color', 32)->nullable();
            $table->string('tube_color', 32)->nullable();
            $table->decimal('length_m', 10, 2)->default(0);
            $table->string('direction_label', 16)->nullable();
            $table->unsignedSmallInteger('bearing_deg')->nullable();
            $table->json('core_map')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'from_node_id']);
            $table->index(['tenant_id', 'to_node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiber_plant_edges');
        Schema::dropIfExists('fiber_plant_nodes');
    }
};
