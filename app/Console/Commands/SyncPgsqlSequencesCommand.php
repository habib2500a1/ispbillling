<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SyncPgsqlSequencesCommand extends Command
{
    protected $signature = 'db:sync-pgsql-sequences';

    protected $description = 'Align PostgreSQL serial sequences with MAX(id) after manual/seed inserts';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->line('Skipped: not using PostgreSQL.');

            return self::SUCCESS;
        }

        $rows = DB::select(<<<'SQL'
            SELECT
                n.nspname AS schema_name,
                c.relname AS table_name,
                a.attname AS column_name
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            JOIN pg_attribute a ON a.attrelid = c.oid
            WHERE c.relkind = 'r'
              AND n.nspname = 'public'
              AND a.attnum > 0
              AND NOT a.attisdropped
              AND pg_get_serial_sequence(format('%I.%I', n.nspname, c.relname), a.attname) IS NOT NULL
        SQL);

        $fixed = 0;

        foreach ($rows as $row) {
            $qualifiedTable = $row->schema_name.'.'.$row->table_name;
            $quotedTable = '"'.$row->schema_name.'"."'.$row->table_name.'"';
            $quotedColumn = '"'.$row->column_name.'"';

            DB::statement(
                "SELECT setval(
                    pg_get_serial_sequence(?, ?),
                    COALESCE((SELECT MAX({$quotedColumn}) FROM {$quotedTable}), 1)
                )",
                [$qualifiedTable, $row->column_name],
            );
            $fixed++;
        }

        $this->info("PostgreSQL sequences synced ({$fixed} columns).");

        return self::SUCCESS;
    }
}
