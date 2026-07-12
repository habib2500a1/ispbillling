<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_sales')) {
            Schema::create('inventory_sales', function (Blueprint $table) {
                $table->id();
                $table->string('sale_number', 64)->unique();
                $table->string('channel', 24)->default('counter'); // counter, issue, field
                $table->string('customer_unique_id')->nullable()->index();
                $table->string('customer_name')->nullable();
                $table->string('customer_phone', 40)->nullable();
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('discount', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->decimal('total_cost', 14, 2)->default(0);
                $table->decimal('gross_profit', 14, 2)->default(0);
                $table->string('payment_method', 32)->default('cash');
                $table->string('status', 24)->default('completed'); // completed, void
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('recorded_by')->nullable()->index();
                $table->timestamp('sold_at')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('inventory_sale_items')) {
            Schema::create('inventory_sale_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_sale_id')->constrained('inventory_sales')->cascadeOnDelete();
                $table->foreignId('inventory_product_id')->constrained('inventory_products')->restrictOnDelete();
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('unit_cost', 12, 2)->default(0);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->decimal('line_total', 14, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_sale_items');
        Schema::dropIfExists('inventory_sales');
    }
};
