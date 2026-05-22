<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('warehouses')) {
            Schema::create('warehouses', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('code', 32);
                $table->string('name');
                $table->string('address')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['tenant_id', 'code']);
            });
        }

        if (! Schema::hasTable('product_warehouse_stock')) {
            Schema::create('product_warehouse_stock', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('stock_qty')->default(0);
                $table->timestamps();
                $table->unique(['product_id', 'warehouse_id']);
                $table->index(['tenant_id', 'warehouse_id']);
            });
        }

        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'barcode')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->string('barcode', 64)->nullable()->after('sku');
                $table->index(['tenant_id', 'barcode']);
            });
        }

        if (Schema::hasTable('stock_movements') && ! Schema::hasColumn('stock_movements', 'warehouse_id')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->foreignId('warehouse_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            });
        }

        if (Schema::hasTable('purchase_orders') && ! Schema::hasColumn('purchase_orders', 'warehouse_id')) {
            Schema::table('purchase_orders', function (Blueprint $table): void {
                $table->foreignId('warehouse_id')->nullable()->after('vendor_id')->constrained()->nullOnDelete();
            });
        }

        if (Schema::hasTable('inventory_sales') && ! Schema::hasColumn('inventory_sales', 'warehouse_id')) {
            Schema::table('inventory_sales', function (Blueprint $table): void {
                $table->foreignId('warehouse_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            });
        }

        if (Schema::hasTable('invoice_items') && ! Schema::hasColumn('invoice_items', 'product_id')) {
            Schema::table('invoice_items', function (Blueprint $table): void {
                $table->foreignId('product_id')->nullable()->after('invoice_id')->constrained()->nullOnDelete();
                $table->foreignId('device_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
                $table->foreignId('warehouse_id')->nullable()->after('device_id')->constrained()->nullOnDelete();
                $table->boolean('stock_issued')->default(false)->after('meta');
            });
        }

        if (Schema::hasTable('devices') && ! Schema::hasColumn('devices', 'product_id')) {
            Schema::table('devices', function (Blueprint $table): void {
                $table->foreignId('product_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            });
        }

        $this->migrateExistingStockToDefaultWarehouses();
    }

    public function down(): void
    {
        if (Schema::hasTable('devices') && Schema::hasColumn('devices', 'product_id')) {
            Schema::table('devices', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('product_id');
            });
        }

        if (Schema::hasTable('invoice_items') && Schema::hasColumn('invoice_items', 'product_id')) {
            Schema::table('invoice_items', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('product_id');
                $table->dropConstrainedForeignId('device_id');
                $table->dropConstrainedForeignId('warehouse_id');
                $table->dropColumn('stock_issued');
            });
        }

        if (Schema::hasTable('inventory_sales') && Schema::hasColumn('inventory_sales', 'warehouse_id')) {
            Schema::table('inventory_sales', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('warehouse_id');
            });
        }

        if (Schema::hasTable('purchase_orders') && Schema::hasColumn('purchase_orders', 'warehouse_id')) {
            Schema::table('purchase_orders', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('warehouse_id');
            });
        }

        if (Schema::hasTable('stock_movements') && Schema::hasColumn('stock_movements', 'warehouse_id')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('warehouse_id');
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'barcode')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropIndex(['tenant_id', 'barcode']);
                $table->dropColumn('barcode');
            });
        }

        Schema::dropIfExists('product_warehouse_stock');
        Schema::dropIfExists('warehouses');
    }

    private function migrateExistingStockToDefaultWarehouses(): void
    {
        if (! Schema::hasTable('warehouses') || ! Schema::hasTable('products')) {
            return;
        }

        $tenantIds = DB::table('products')->distinct()->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            $warehouseId = DB::table('warehouses')
                ->where('tenant_id', $tenantId)
                ->where('is_default', true)
                ->value('id');

            if (! $warehouseId) {
                $warehouseId = DB::table('warehouses')->insertGetId([
                    'tenant_id' => $tenantId,
                    'code' => 'MAIN',
                    'name' => 'Main warehouse',
                    'is_default' => true,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (! Schema::hasTable('product_warehouse_stock')) {
                continue;
            }

            $products = DB::table('products')->where('tenant_id', $tenantId)->get(['id', 'stock_qty']);

            foreach ($products as $product) {
                $exists = DB::table('product_warehouse_stock')
                    ->where('product_id', $product->id)
                    ->where('warehouse_id', $warehouseId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $qty = max(0, (int) $product->stock_qty);
                if ($qty === 0) {
                    continue;
                }

                DB::table('product_warehouse_stock')->insert([
                    'tenant_id' => $tenantId,
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouseId,
                    'stock_qty' => $qty,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (Schema::hasTable('stock_movements') && Schema::hasColumn('stock_movements', 'warehouse_id')) {
                DB::table('stock_movements')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('warehouse_id')
                    ->update(['warehouse_id' => $warehouseId]);
            }
        }

        if (Schema::hasTable('warehouses') && DB::table('warehouses')->count() === 0) {
            $tenantId = (int) (DB::table('tenants')->orderBy('id')->value('id') ?? 1);
            DB::table('warehouses')->insert([
                'tenant_id' => $tenantId,
                'code' => 'MAIN',
                'name' => 'Main warehouse',
                'is_default' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
