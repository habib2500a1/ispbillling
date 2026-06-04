<?php

namespace App\Console\Commands;

use App\Services\Import\LegacyPortalBillingImporter;
use App\Services\Import\LegacyPortalCollectionReconcileService;
use App\Services\Import\LegacyPortalSessionClient;
use Illuminate\Console\Command;

class SyncLegacyPortalCollectionsCommand extends Command
{
    protected $signature = 'isp:sync-legacy-portal-collections
                            {--customer= : Only this customer_code (e.g. 219)}
                            {--void-orphans : Void duplicate local desk/wallet rows when legacy portal already has the payment}
                            {--no-import : Skip importing missing rows from legacy portal}
                            {--dry-run : Report only; with --void-orphans shows what would be voided}';

    protected $description = 'Match local collection history with legacy portal (import missing + optional void duplicates)';

    public function handle(LegacyPortalCollectionReconcileService $reconcile): int
    {
        $password = (string) config('legacy_portal.password');
        if ($password === '') {
            $this->error('Set LEGACY_PORTAL_PASSWORD in .env');

            return self::FAILURE;
        }

        $client = new LegacyPortalSessionClient(
            (string) config('legacy_portal.base_url'),
            (string) config('legacy_portal.username'),
            $password,
        );

        $this->info('Logging in to legacy portal…');
        $client->login();

        $dryRun = (bool) $this->option('dry-run');
        $voidOrphans = (bool) $this->option('void-orphans');
        $importMissing = ! (bool) $this->option('no-import');
        $customerCode = trim((string) $this->option('customer'));

        if ($dryRun) {
            $this->warn('Dry run — no data changes'.($voidOrphans ? ' (orphans counted only)' : ''));
        }

        $stats = $reconcile->reconcileAll(
            $client,
            $importMissing,
            $voidOrphans,
            $dryRun,
            $customerCode !== '' ? $customerCode : null,
        );

        $this->table(['Metric', 'Count'], [
            ['Subscribers scanned', $stats['customers']],
            ['Payments imported from legacy portal', $stats['imported']],
            ['Import skipped (already exists)', $stats['skipped_import']],
            ['legacy portal payment rows (remote)', $stats['legacy_portal_rows']],
            ['Local rows tagged legacy portal', $stats['local_legacy_portal']],
            ['Local-only rows (not from legacy portal)', $stats['local_only']],
            ['Safe auto-void candidates', $stats['orphan_candidates']],
            ['Voided (or would void)', $stats['voided']],
            ['Void blocked', $stats['void_blocked']],
        ]);

        foreach ($stats['errors'] as $error) {
            $this->warn($error);
        }

        if ($stats['local_only'] > 0 && ! $voidOrphans) {
            $this->newLine();
            $this->line('Re-run with <fg=yellow>--void-orphans</> to remove duplicate local desk/wallet entries when legacy portal already has the collection.');
        }

        $this->newLine();
        $this->line('Collection report: use filter <fg=cyan>legacy portal (pay.anetbd)</> to match the old portal.');
        $this->line('Desk-only entries are separate until recorded in legacy portal too.');

        return self::SUCCESS;
    }
}
