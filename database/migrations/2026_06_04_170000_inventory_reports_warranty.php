<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                if (! Schema::hasColumn('products', 'damaged_qty')) {
                    $table->unsignedInteger('damaged_qty')->default(0)->after('stock_qty');
                }
                if (! Schema::hasColumn('products', 'missing_qty')) {
                    $table->unsignedInteger('missing_qty')->default(0)->after('damaged_qty');
                }
            });
        }

        if (Schema::hasTable('devices')) {
            Schema::table('devices', function (Blueprint $table): void {
                if (! Schema::hasColumn('devices', 'warranty_vendor')) {
                    $table->string('warranty_vendor', 128)->nullable()->after('notes');
                }
                if (! Schema::hasColumn('devices', 'warranty_started_at')) {
                    $table->date('warranty_started_at')->nullable()->after('warranty_vendor');
                }
                if (! Schema::hasColumn('devices', 'warranty_expires_at')) {
                    $table->date('warranty_expires_at')->nullable()->after('warranty_started_at');
                }
                if (! Schema::hasColumn('devices', 'warranty_status')) {
                    $table->string('warranty_status', 32)->nullable()->after('warranty_expires_at');
                }
                if (! Schema::hasColumn('devices', 'warranty_claimed_at')) {
                    $table->date('warranty_claimed_at')->nullable()->after('warranty_status');
                }
                if (! Schema::hasColumn('devices', 'warranty_notes')) {
                    $table->text('warranty_notes')->nullable()->after('warranty_claimed_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                foreach (['damaged_qty', 'missing_qty'] as $col) {
                    if (Schema::hasColumn('products', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('devices')) {
            Schema::table('devices', function (Blueprint $table): void {
                foreach ([
                    'warranty_vendor',
                    'warranty_started_at',
                    'warranty_expires_at',
                    'warranty_status',
                    'warranty_claimed_at',
                    'warranty_notes',
                ] as $col) {
                    if (Schema::hasColumn('devices', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
