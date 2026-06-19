<?php

namespace App\Console\Commands;

use App\Support\LegacyPortalRemovalManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Remove pay.anetbd.com sync/import code when native billing is the only source of truth.
 *
 * Usage:
 *   php artisan isp:remove-legacy-portal              # preview
 *   php artisan isp:remove-legacy-portal --force      # delete files + disable scheduler + stub config
 *   php artisan isp:remove-legacy-portal --force --include-data-helpers
 */
final class RemoveLegacyPortalCommand extends Command
{
    protected $signature = 'isp:remove-legacy-portal
                            {--force : Actually delete files and apply patches (default is dry-run)}
                            {--include-data-helpers : Also remove LegacyPortalSource and related read helpers}
                            {--drop-mirror-tables : Drop legacy_portal_mirror_* tables after removal}';

    protected $description = 'Remove legacy portal (pay.anetbd.com) sync/import code in one command';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $dryRun = ! $force;

        if ($dryRun) {
            $this->warn('DRY RUN — pass --force to delete files and patch the app.');
        } elseif (! $this->option('no-interaction') && ! $this->confirm('This permanently removes legacy portal sync code. Continue?', false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $root = base_path();
        $files = LegacyPortalRemovalManifest::deletableFiles();

        if ((bool) $this->option('include-data-helpers')) {
            $files = array_merge($files, LegacyPortalRemovalManifest::historicalDataHelpers());
            $this->warn('Including historical data helpers — ensure no import_source=legacy_portal logic remains.');
        }

        $deleted = 0;
        $missing = 0;

        foreach ($files as $relative) {
            $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (! File::exists($path)) {
                $missing++;
                $this->line("  skip (missing): {$relative}");

                continue;
            }

            if ($dryRun) {
                $this->line("  would delete: {$relative}");
            } else {
                File::delete($path);
                $this->line("  deleted: {$relative}");
            }
            $deleted++;
        }

        if (! $dryRun) {
            $this->writeCutoverConfig();
            $this->patchScheduler();
            $this->patchEnvExample();
            $this->writeStubProviders();

            if ((bool) $this->option('drop-mirror-tables')) {
                $this->dropMirrorTables();
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d file(s)%s.',
            $dryRun ? 'Would remove' : 'Removed',
            $deleted,
            $missing > 0 ? " ({$missing} already missing)" : '',
        ));

        $this->newLine();
        $this->comment('Review these mixed files (legacy references may remain):');
        foreach (LegacyPortalRemovalManifest::mixedIntegrationFiles() as $mixed) {
            $this->line("  · {$mixed}");
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('When ready: php artisan isp:remove-legacy-portal --force');
        } else {
            $this->newLine();
            $this->info('Next: php artisan optimize:clear && php artisan config:cache');
            $this->info('Optional: git add -A && git commit -m "Remove legacy portal integration"');
        }

        return self::SUCCESS;
    }

    private function writeCutoverConfig(): void
    {
        $stub = <<<'PHP'
<?php

/**
 * Legacy portal integration removed (isp:remove-legacy-portal).
 * Kept so config('legacy_portal.*') calls in mixed code return safe defaults.
 */
return [
    'daily_sync_enabled' => false,
    'collections_sync_every_minutes' => 0,
    'raw_mirror_enabled' => false,
    'portal_label' => 'Billing',
    'collection_report_default_source' => 'desk',
    'show_dashboard_kpi_hint' => false,
];
PHP;

        File::put(config_path('legacy_portal.php'), $stub);
        File::put(config_path('isp_digital.php'), "<?php\n\nreturn require __DIR__.'/legacy_portal.php';\n");
        $this->line('  wrote: config/legacy_portal.php (cutover stub)');
    }

    private function writeStubProviders(): void
    {
        $dashboardStub = <<<'PHP'
<?php

namespace App\Services\Import;

/** @internal Stub after legacy portal removal — always disabled. */
final class LegacyPortalDashboardSummaryProvider
{
    public function tenantUsesLegacyPortal(int $tenantId): bool
    {
        return false;
    }

    public function summary(int $tenantId, bool $allowRemoteRefresh = true): ?array
    {
        return null;
    }

    public function refreshFromRemote(int $tenantId): ?array
    {
        return null;
    }

    public function storeSummary(int $tenantId, array $summary): void {}
}
PHP;

        $passwordStub = <<<'PHP'
<?php

namespace App\Support;

/** @internal Stub after legacy portal removal. */
final class LegacyPortalPassword
{
    public static function resolve(?string $override = null): string
    {
        return '';
    }
}
PHP;

        File::ensureDirectoryExists(app_path('Services/Import'));
        File::put(app_path('Services/Import/LegacyPortalDashboardSummaryProvider.php'), $dashboardStub);
        File::put(app_path('Support/LegacyPortalPassword.php'), $passwordStub);
        $this->line('  wrote: stubs (LegacyPortalDashboardSummaryProvider, LegacyPortalPassword)');
    }

    private function patchScheduler(): void
    {
        $path = base_path('bootstrap/app.php');
        $contents = File::get($path);

        $legacyBlock = <<<'PHP'

        $schedule->command('isp:sync-legacy-portal-daily')
            ->dailyAt((string) config('legacy_portal.daily_sync_at', '02:30'))
            ->withoutOverlapping(180)
            ->onOneServer()
            ->when(fn (): bool => (bool) config('legacy_portal.daily_sync_enabled', true));

        $legacyCollectionMinutes = max(0, min(59, (int) config('legacy_portal.collections_sync_every_minutes', 15)));
        if ($legacyCollectionMinutes > 0) {
            $schedule->command('isp:sync-legacy-portal-collections', array_filter([
                '--void-orphans' => (bool) config('legacy_portal.sync_collections_void_orphans', true),
                '--password' => \App\Support\LegacyPortalPassword::resolve(),
            ]))
                ->cron('*/'.$legacyCollectionMinutes.' * * * *')
                ->withoutOverlapping(max(10, $legacyCollectionMinutes))
                ->onOneServer()
                ->when(fn (): bool => (bool) config('legacy_portal.daily_sync_enabled', true)
                    && \App\Support\LegacyPortalPassword::resolve() !== '');
        }

        $schedule->command('isp:mirror-legacy-portal --with-customer-details --with-history')
            ->dailyAt((string) config('legacy_portal.raw_mirror_at', '01:15'))
            ->withoutOverlapping(360)
            ->onOneServer()
            ->when(fn (): bool => (bool) config('legacy_portal.raw_mirror_enabled', false));
PHP;

        if (str_contains($contents, $legacyBlock)) {
            $contents = str_replace(
                $legacyBlock,
                "\n        // Legacy portal scheduler removed (isp:remove-legacy-portal).",
                $contents,
            );
        }

        File::put($path, $contents);
        $this->line('  patched: bootstrap/app.php (scheduler)');
    }

    private function patchEnvExample(): void
    {
        $path = base_path('.env.example');
        if (! File::exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        $keys = array_flip(LegacyPortalRemovalManifest::envKeys());
        $filtered = array_filter($lines, function (string $line) use ($keys): bool {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                return true;
            }
            $key = strtoupper(strtok($trimmed, '='));

            return ! isset($keys[$key]);
        });

        File::put($path, implode(PHP_EOL, $filtered).PHP_EOL);
        $this->line('  patched: .env.example (legacy keys removed)');
    }

    private function dropMirrorTables(): void
    {
        if (! $this->confirm('Drop legacy_portal_mirror_runs and legacy_portal_mirror_records tables?', false)) {
            return;
        }

        $this->call('migrate:rollback', [
            '--path' => 'database/migrations/2026_06_26_010000_create_legacy_portal_mirror_tables.php',
            '--force' => true,
        ]);
    }
}
