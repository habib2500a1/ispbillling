<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Safe recurring sync while legacy portal (pay.anetbd.com) is still live.
 * Saves subscribers, billing due, and collections locally — does not bulk-enable network.
 *
 * Disable later: LEGACY_PORTAL_DAILY_SYNC_ENABLED=false
 */
class SyncLegacyPortalDailyCommand extends Command
{
    protected $signature = 'isp:sync-legacy-portal-daily
                            {--skip-import : Skip subscriber list refresh}
                            {--skip-billing : Skip current-month billing grid}
                            {--skip-collections : Skip payment history import}';

    protected $description = 'Daily legacy portal pull: subscribers, billing due, collections (no bulk network ON)';

    public function handle(): int
    {
        if (! (bool) config('legacy_portal.daily_sync_enabled', true)) {
            $this->warn('legacy portal daily sync is off (LEGACY_PORTAL_DAILY_SYNC_ENABLED=false).');

            return self::SUCCESS;
        }

        $connection = array_filter([
            '--url' => config('legacy_portal.base_url'),
            '--user' => config('legacy_portal.username'),
            '--password' => config('legacy_portal.password'),
        ], fn ($v): bool => $v !== null && $v !== '');

        if ((string) config('legacy_portal.password') === '' && ! $this->option('url')) {
            $this->error('Set LEGACY_PORTAL_PASSWORD in .env (or pass --url/--user/--password).');

            return self::FAILURE;
        }

        if (! $this->option('skip-import')) {
            $this->info('1/4 — Refresh subscribers from legacy portal…');
            if ($this->call('isp:import-legacy-portal', array_merge($connection, [
                '--all' => true,
                '--force' => true,
                '--batch' => (int) config('legacy_portal.daily_sync_import_batch', 100),
            ])) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        if (! $this->option('skip-billing')) {
            $this->info('2/4 — Sync billing grid (due = ISP BalanceDue)…');
            if ($this->call('isp:sync-legacy-portal-current-billing', $connection) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        if (! $this->option('skip-collections')) {
            $this->info('3/4 — Sync collections (bKash, cash, …)…');
            $voidOrphans = (bool) config('legacy_portal.sync_collections_void_orphans', true);
            if ($this->call('isp:sync-legacy-portal-collections', array_merge(
                $connection,
                $voidOrphans ? ['--void-orphans' => true] : [],
            )) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        $this->info('4/4 — Sync subscriber lifecycle (suspended / VIP / free / inactive)…');
        if ($this->call('isp:sync-legacy-portal-lifecycle') !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('legacy portal daily sync finished — data saved in this panel.');
        $this->line('Network was not bulk-enabled. To turn lines ON use isp:align-legacy-portal (without --skip-network).');
        $this->line('To stop syncing later: LEGACY_PORTAL_DAILY_SYNC_ENABLED=false in .env');

        return self::SUCCESS;
    }
}
