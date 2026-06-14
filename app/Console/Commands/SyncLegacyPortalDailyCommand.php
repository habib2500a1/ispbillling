<?php

namespace App\Console\Commands;

use App\Support\LegacyPortalPassword;
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
                            {--skip-collections : Skip payment history import}
                            {--skip-details : Skip customer details / ONU meta sync}
                            {--skip-extras : Skip SMS, resellers, app users, collectors, service invoices}
                            {--skip-onu : Skip optical ONU discovery/signal pipeline}
                            {--skip-verify : Skip post-sync verification report}
                            {--url= : Override LEGACY_PORTAL_URL}
                            {--user= : Override LEGACY_PORTAL_USERNAME}
                            {--password= : Override LEGACY_PORTAL_PASSWORD}';

    protected $description = 'Daily legacy portal pull: subscribers, status/grace, billing, collections, SMS/resellers/details/ONU (no bulk network ON)';

    public function handle(): int
    {
        if (! (bool) config('legacy_portal.daily_sync_enabled', true)) {
            $this->warn('legacy portal daily sync is off (LEGACY_PORTAL_DAILY_SYNC_ENABLED=false).');

            return self::SUCCESS;
        }

        $password = LegacyPortalPassword::resolve((string) $this->option('password'));
        if ($password === '') {
            $this->error('Set LEGACY_PORTAL_PASSWORD (not KEEP_CURRENT) or LEGACY_PORTAL_SYNC_PASSWORD in .env (or pass --password=).');

            return self::FAILURE;
        }

        $connection = array_filter([
            '--url' => $this->option('url') ?: config('legacy_portal.base_url'),
            '--user' => $this->option('user') ?: config('legacy_portal.username'),
            '--password' => $password,
        ], fn ($v): bool => $v !== null && $v !== '');

        if (! $this->option('skip-import')) {
            $this->info('1/9 — Refresh subscribers from legacy portal…');
            if ($this->call('isp:import-legacy-portal', array_merge($connection, [
                '--all' => true,
                '--force' => true,
                '--batch' => (int) config('legacy_portal.daily_sync_import_batch', 100),
            ])) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        if (! $this->option('skip-billing')) {
            $this->info('2/9 — Sync billing grid (due = ISP BalanceDue)…');
            if ($this->call('isp:sync-legacy-portal-current-billing', $connection) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        if (! $this->option('skip-collections')) {
            $this->info('3/9 — Sync collections (bKash, cash, …)…');
            $voidOrphans = (bool) config('legacy_portal.sync_collections_void_orphans', true);
            if ($this->call('isp:sync-legacy-portal-collections', array_merge(
                $connection,
                $voidOrphans ? ['--void-orphans' => true] : [],
            )) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        $this->info('4/9 — Sync subscriber lifecycle (active / expired / suspended / VIP / free / inactive)…');
        if ($this->call('isp:sync-legacy-portal-lifecycle') !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->info('5/9 — Sync line grace / overdue state…');
        if ($this->call('isp:sync-legacy-portal-line-grace', [
            '--resolve-alerts' => true,
        ]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        if (! $this->option('skip-details')) {
            $this->info('6/9 — Sync customer details / ONU fields…');
            if ($this->call('isp:sync-legacy-portal-details', array_merge(
                $connection,
                (bool) config('legacy_portal.daily_sync_force_details', false) ? ['--force' => true] : [],
            )) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        if (! $this->option('skip-extras')) {
            $this->info('7/9 — Sync SMS, resellers, app users, collectors, service invoices…');
            if ($this->call('isp:import-legacy-portal-extras', array_merge(
                $connection,
                (bool) config('legacy_portal.daily_sync_force_extras', false) ? ['--force' => true] : [],
            )) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        if (! $this->option('skip-onu') && (bool) config('legacy_portal.daily_sync_onu_enabled', true)) {
            $this->info('8/9 — Sync OLT/ONU inventory and signal snapshots…');
            if ($this->call('isp:legacy-portal-onu-sync') !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        if (! $this->option('skip-verify')) {
            $this->info('9/9 — Verify source/local counts and missing mirror coverage…');
            $this->call('isp:verify-legacy-portal-full-sync', array_merge($connection, [
                '--sample' => (int) config('legacy_portal.daily_sync_verify_sample', 10),
            ]));
        }

        $this->newLine();
        $this->info('legacy portal daily sync finished — data saved in this panel.');
        $this->line('Network was not bulk-enabled. To turn lines ON use isp:align-legacy-portal (without --skip-network).');
        $this->line('To stop syncing later: LEGACY_PORTAL_DAILY_SYNC_ENABLED=false in .env');

        return self::SUCCESS;
    }
}
