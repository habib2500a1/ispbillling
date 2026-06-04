<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive performance indexes for reseller analytics dashboards.
 * Index names are guarded so the migration is safe to re-run and won't clash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reseller_commissions', function (Blueprint $table): void {
            if (! $this->hasIndex('reseller_commissions', 'reseller_commissions_reseller_status_idx')) {
                $table->index(['reseller_id', 'status'], 'reseller_commissions_reseller_status_idx');
            }
            if (! $this->hasIndex('reseller_commissions', 'reseller_commissions_earned_status_idx')) {
                $table->index(['earned_at', 'status'], 'reseller_commissions_earned_status_idx');
            }
        });

        if (Schema::hasTable('reseller_settlements')) {
            Schema::table('reseller_settlements', function (Blueprint $table): void {
                if (! $this->hasIndex('reseller_settlements', 'reseller_settlements_reseller_status_idx')) {
                    $table->index(['reseller_id', 'status', 'submitted_at'], 'reseller_settlements_reseller_status_idx');
                }
            });
        }

        if (Schema::hasTable('reseller_wallet_recharge_requests')) {
            Schema::table('reseller_wallet_recharge_requests', function (Blueprint $table): void {
                if (! $this->hasIndex('reseller_wallet_recharge_requests', 'reseller_recharge_status_idx')) {
                    $table->index(['status', 'reseller_id'], 'reseller_recharge_status_idx');
                }
            });
        }

        Schema::table('customers', function (Blueprint $table): void {
            if (! $this->hasIndex('customers', 'customers_reseller_status_idx')) {
                $table->index(['reseller_id', 'status'], 'customers_reseller_status_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reseller_commissions', function (Blueprint $table): void {
            $table->dropIndex('reseller_commissions_reseller_status_idx');
            $table->dropIndex('reseller_commissions_earned_status_idx');
        });
        if (Schema::hasTable('reseller_settlements')) {
            Schema::table('reseller_settlements', fn (Blueprint $t) => $t->dropIndex('reseller_settlements_reseller_status_idx'));
        }
        if (Schema::hasTable('reseller_wallet_recharge_requests')) {
            Schema::table('reseller_wallet_recharge_requests', fn (Blueprint $t) => $t->dropIndex('reseller_recharge_status_idx'));
        }
        Schema::table('customers', fn (Blueprint $t) => $t->dropIndex('customers_reseller_status_idx'));
    }

    private function hasIndex(string $table, string $index): bool
    {
        try {
            $conn = Schema::getConnection();
            $sm = $conn->getDoctrineSchemaManager();
            return array_key_exists($index, $sm->listTableIndexes($table));
        } catch (\Throwable) {
            // Doctrine DBAL not available — fall back to a raw lookup (MySQL/Postgres tolerant).
            try {
                $driver = Schema::getConnection()->getDriverName();
                if ($driver === 'pgsql') {
                    return (bool) DB::selectOne('SELECT 1 FROM pg_indexes WHERE indexname = ?', [$index]);
                }
                return (bool) DB::selectOne(
                    "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND index_name = ?",
                    [$index]
                );
            } catch (\Throwable) {
                return false;
            }
        }
    }
};
