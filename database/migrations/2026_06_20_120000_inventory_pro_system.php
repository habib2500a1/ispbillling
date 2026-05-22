<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('cost_price', 12, 2)->default(0)->after('unit_price');
            $table->decimal('sell_price', 12, 2)->default(0)->after('cost_price');
            $table->decimal('last_purchase_cost', 12, 2)->nullable()->after('sell_price');
            $table->text('description')->nullable()->after('name');
            $table->boolean('show_on_shop')->default(false)->after('is_active');
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->integer('quantity');
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->unsignedInteger('stock_before')->default(0);
            $table->unsignedInteger('stock_after')->default(0);
            $table->string('reference_type', 64)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moved_at');
            $table->timestamps();
            $table->index(['tenant_id', 'product_id', 'moved_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('inventory_sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('sale_number', 64);
            $table->string('channel', 32)->default('counter');
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 32)->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->decimal('gross_profit', 12, 2)->default(0);
            $table->string('payment_method', 32)->default('cash');
            $table->string('status', 32)->default('completed');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sold_at');
            $table->timestamps();
            $table->unique(['tenant_id', 'sale_number']);
        });

        Schema::create('inventory_sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->decimal('line_cost', 12, 2)->default(0);
            $table->decimal('line_profit', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_sale_items');
        Schema::dropIfExists('inventory_sales');
        Schema::dropIfExists('stock_movements');
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'cost_price',
                'sell_price',
                'last_purchase_cost',
                'description',
                'show_on_shop',
            ]);
        });
    }
};
