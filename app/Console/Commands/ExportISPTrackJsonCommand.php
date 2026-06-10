<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportISPTrackJsonCommand extends Command
{
    protected $signature = 'isp:export-isptrack-json
        {--output=storage/app/import/isptrack-export.json : Output JSON path}
        {--connection= : Laravel DB connection name (overrides ISPTrack env)}';

    protected $description = 'Export ISPTrack MySQL tables to JSON for isp:import-isptrack';

    public function handle(): int
    {
        $connection = $this->option('connection');
        if ($connection) {
            $db = DB::connection((string) $connection);
        } else {
            $cfg = config('isptrack.db');
            config([
                'database.connections.isptrack_export' => [
                    'driver' => 'mysql',
                    'host' => $cfg['host'],
                    'port' => $cfg['port'],
                    'database' => $cfg['database'],
                    'username' => $cfg['username'],
                    'password' => $cfg['password'],
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                ],
            ]);
            $db = DB::connection('isptrack_export');
        }

        try {
            $db->getPdo();
        } catch (\Throwable $e) {
            $this->error('Cannot connect to ISPTrack database: '.$e->getMessage());
            $this->line('Set ISPTRACK_DB_* in .env or pass --connection=');

            return self::FAILURE;
        }

        $payload = [
            'meta' => [
                'source' => 'isptrack',
                'exported_at' => now()->toIso8601String(),
            ],
            'packages' => $this->tableOrEmpty($db, 'packages'),
            'zones' => $this->tableOrEmpty($db, 'zones'),
            'sub_zones' => $this->tableOrEmpty($db, 'sub_zones'),
            'boxes' => $this->tableOrEmpty($db, 'boxes'),
            'mikrotik_servers' => $this->tableOrEmpty($db, 'mikrotik_servers'),
            'clients' => $this->tableOrEmpty($db, 'clients'),
            'billings' => $this->tableOrEmpty($db, 'billings'),
            'invoices' => $this->tableOrEmpty($db, 'invoices'),
            'payments' => $this->tableOrEmpty($db, 'payments'),
        ];

        $path = (string) $this->option('output');
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Exported to {$path}");
        $this->table(
            ['Section', 'Rows'],
            collect($payload)->except('meta')->map(fn ($rows, $key) => [$key, is_array($rows) ? count($rows) : 0])->values()->all(),
        );

        return self::SUCCESS;
    }

    private function tableOrEmpty(\Illuminate\Database\Connection $db, string $table): array
    {
        try {
            return $db->table($table)->get()->map(fn ($row) => (array) $row)->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
