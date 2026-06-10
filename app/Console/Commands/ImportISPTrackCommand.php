<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Import\ISPTrack\ISPTrackImportOrchestrator;
use App\Support\TenantResolver;
use Illuminate\Console\Command;

class ImportISPTrackCommand extends Command
{
    protected $signature = 'isp:import-isptrack
        {path : Path to ISPTrack JSON export}
        {--tenant= : Tenant ID (defaults to first tenant)}
        {--phase=* : Run phase(s) only: 0=prep, 1=master, 2=customers, 3=billing, 4=network, 5=verify (default: all)}
        {--dry-run : Validate and count without writing}
        {--force : Update existing rows matched by code/name}
        {--skip-network : Phase 4: skip due refresh and network evaluate}';

    protected $description = 'Import ISPTrack export JSON in 5 phases (prep → master → customers → billing → network → verify)';

    public function handle(ISPTrackImportOrchestrator $orchestrator): int
    {
        $path = (string) $this->argument('path');
        if (! is_readable($path)) {
            $this->error("File not readable: {$path}");

            return self::FAILURE;
        }

        $tenantId = $this->option('tenant') !== null
            ? (int) $this->option('tenant')
            : (int) (TenantResolver::currentTenantId() ?? Tenant::query()->value('id'));

        if ($tenantId <= 0) {
            $this->error('No tenant found. Pass --tenant=ID');

            return self::FAILURE;
        }

        $phases = $this->resolvePhases();
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $skipNetwork = (bool) $this->option('skip-network');

        TenantResolver::fake($tenantId);

        $this->info('ISPTrack import');
        $this->line("  tenant={$tenantId} · phases=".implode(',', $phases).($dryRun ? ' · DRY RUN' : ''));

        $report = $orchestrator->run($path, $tenantId, $phases, $dryRun, $force, $skipNetwork);

        foreach ($report['phases'] as $phase => $payload) {
            $this->newLine();
            $this->info('Phase '.$phase);

            if ($phase === 0 && is_array($payload)) {
                $preview = $payload['mikrotik_preview'] ?? [];
                unset($payload['mikrotik_preview']);
                $this->table(
                    ['Key', 'Value'],
                    collect($payload)->map(fn ($v, $k) => [$k, is_scalar($v) ? $v : json_encode($v)])->values()->all(),
                );
                if (is_array($preview) && $preview !== []) {
                    $this->newLine();
                    $this->info('MikroTik server match preview');
                    $this->table(
                        ['Export', 'Host', 'Local ID', 'Local name', 'Status'],
                        collect($preview)->map(fn (array $row) => [
                            $row['export_name'] ?? '',
                            $row['export_host'] ?? '',
                            $row['local_id'] ?? '—',
                            $row['local_name'] ?? '—',
                            $row['status'] ?? '',
                        ])->all(),
                    );
                }
            } elseif ($phase === 5 && is_array($payload)) {
                $this->table(['Metric', 'Expected', 'Actual', 'OK'], $payload);
            } elseif (is_array($payload)) {
                $rows = collect($payload)
                    ->filter(fn ($v) => is_int($v) || is_string($v))
                    ->map(fn ($v, $k) => [$k, $v])
                    ->values()
                    ->all();
                if ($rows !== []) {
                    $this->table(['Metric', 'Count'], $rows);
                }
            }
        }

        if (isset($report['stats']) && is_array($report['stats']) && $report['stats'] !== []) {
            $this->newLine();
            $this->info('Cumulative stats');
            $this->table(
                ['Metric', 'Count'],
                collect($report['stats'])->map(fn ($v, $k) => [$k, $v])->values()->all(),
            );
        }

        return self::SUCCESS;
    }

    /** @return list<int> */
    private function resolvePhases(): array
    {
        $requested = array_map('intval', (array) $this->option('phase'));
        $requested = array_values(array_filter($requested, fn (int $p): bool => $p >= 0 && $p <= 5));

        if ($requested === []) {
            return [0, 1, 2, 3, 4, 5];
        }

        sort($requested);

        return $requested;
    }
}
