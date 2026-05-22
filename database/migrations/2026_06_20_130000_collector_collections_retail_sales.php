<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('collector_collections', 'inventory_sale_id')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=OFF');
            Schema::rename('collector_collections', 'collector_collections_old');

            foreach (DB::select("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = 'collector_collections_old'") as $index) {
                $name = $index->name ?? null;
                if ($name && ! str_starts_with($name, 'sqlite_autoindex')) {
                    DB::statement('DROP INDEX IF EXISTS "'.$name.'"');
                }
            }

            Schema::create('collector_collections', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
                $table->foreignId('payment_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('inventory_sale_id')->nullable()->constrained('inventory_sales')->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
                $table->foreignId('collector_id')->constrained('users')->restrictOnDelete();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->decimal('amount', 14, 2);
                $table->decimal('amount_settled', 14, 2)->default(0);
                $table->string('payment_method', 32)->default('cash');
                $table->string('status', 16)->default('open');
                $table->timestamp('collected_at');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique('inventory_sale_id');
                $table->index(['tenant_id', 'collector_id', 'status']);
                $table->index(['collector_id', 'collected_at']);
            });

            DB::statement('INSERT INTO collector_collections (id, tenant_id, payment_id, customer_id, collector_id, branch_id, amount, amount_settled, payment_method, status, collected_at, notes, created_at, updated_at)
                SELECT id, tenant_id, payment_id, customer_id, collector_id, branch_id, amount, amount_settled, payment_method, status, collected_at, notes, created_at, updated_at FROM collector_collections_old');

            Schema::drop('collector_collections_old');
            DB::statement('PRAGMA foreign_keys=ON');

            return;
        }

        Schema::table('collector_collections', function (Blueprint $table): void {
            $table->foreignId('inventory_sale_id')->nullable()->after('payment_id')
                ->constrained('inventory_sales')->nullOnDelete();
            $table->unique('inventory_sale_id');
        });

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE collector_collections MODIFY payment_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE collector_collections MODIFY customer_id BIGINT UNSIGNED NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE collector_collections ALTER COLUMN payment_id DROP NOT NULL');
            DB::statement('ALTER TABLE collector_collections ALTER COLUMN customer_id DROP NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('collector_collections', 'inventory_sale_id')) {
            return;
        }

        Schema::table('collector_collections', function (Blueprint $table): void {
            $table->dropUnique(['inventory_sale_id']);
            $table->dropConstrainedForeignId('inventory_sale_id');
        });
    }
};
