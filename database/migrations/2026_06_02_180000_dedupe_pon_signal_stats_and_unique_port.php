<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pon_signal_stats')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('
                DELETE FROM pon_signal_stats AS older
                USING pon_signal_stats AS newer
                WHERE older.tenant_id = newer.tenant_id
                  AND older.olt_id = newer.olt_id
                  AND older.card_no IS NOT DISTINCT FROM newer.card_no
                  AND older.pon_no IS NOT DISTINCT FROM newer.pon_no
                  AND older.id < newer.id
            ');
        } elseif ($driver === 'sqlite') {
            DB::statement('
                DELETE FROM pon_signal_stats
                WHERE id NOT IN (
                    SELECT MAX(id)
                    FROM pon_signal_stats
                    GROUP BY tenant_id, olt_id, card_no, pon_no
                )
            ');
        } else {
            DB::statement('
                DELETE p1 FROM pon_signal_stats p1
                INNER JOIN pon_signal_stats p2
                    ON p1.tenant_id = p2.tenant_id
                    AND p1.olt_id = p2.olt_id
                    AND p1.card_no <=> p2.card_no
                    AND p1.pon_no <=> p2.pon_no
                    AND p1.id < p2.id
            ');
        }

        Schema::table('pon_signal_stats', function (Blueprint $table): void {
            $table->unique(
                ['tenant_id', 'olt_id', 'card_no', 'pon_no'],
                'pon_signal_stats_tenant_olt_card_pon_unique',
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pon_signal_stats')) {
            return;
        }

        Schema::table('pon_signal_stats', function (Blueprint $table): void {
            $table->dropUnique('pon_signal_stats_tenant_olt_card_pon_unique');
        });
    }
};
