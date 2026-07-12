<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_products')) {
            Schema::create('inventory_products', function (Blueprint $table) {
                $table->id();
                $table->string('sku', 64)->nullable()->unique();
                $table->string('name');
                $table->string('category', 64)->nullable()->index();
                $table->string('unit', 24)->default('pcs');
                $table->integer('stock_qty')->default(0);
                $table->integer('reorder_level')->default(0);
                $table->decimal('cost_price', 12, 2)->default(0);
                $table->decimal('sell_price', 12, 2)->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('inventory_stock_movements')) {
            Schema::create('inventory_stock_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_product_id')->constrained('inventory_products')->cascadeOnDelete();
                $table->string('type', 24); // in, out, adjust
                $table->integer('quantity'); // signed delta applied to stock
                $table->integer('stock_after')->default(0);
                $table->decimal('unit_cost', 12, 2)->default(0);
                $table->string('reference', 120)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('staff_user_id')->nullable()->index();
                $table->timestamp('moved_at')->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_movements');
        Schema::dropIfExists('inventory_products');
    }
};
