<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_warehouses')) {
            Schema::create('inventory_warehouses', function (Blueprint $table) {
                $table->id();
                $table->string('code', 32)->nullable()->unique();
                $table->string('name');
                $table->string('address')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('inventory_purchase_orders')) {
            Schema::create('inventory_purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->string('po_number', 64)->unique();
                $table->string('vendor_name')->nullable();
                $table->foreignId('warehouse_id')->nullable()->constrained('inventory_warehouses')->nullOnDelete();
                $table->string('status', 24)->default('draft')->index(); // draft, ordered, received, cancelled
                $table->decimal('total', 14, 2)->default(0);
                $table->date('ordered_at')->nullable();
                $table->date('received_at')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('inventory_purchase_order_items')) {
            Schema::create('inventory_purchase_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_order_id')->constrained('inventory_purchase_orders')->cascadeOnDelete();
                $table->foreignId('inventory_product_id')->constrained('inventory_products')->restrictOnDelete();
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('unit_cost', 12, 2)->default(0);
                $table->decimal('line_total', 14, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_purchase_order_items');
        Schema::dropIfExists('inventory_purchase_orders');
        Schema::dropIfExists('inventory_warehouses');
    }
};
