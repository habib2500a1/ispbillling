<?php

namespace App\Console\Commands;

use App\Services\Dashboard\DashboardPreferencesService;
use App\Support\EnsureStorageWritable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class ProductionAuditCommand extends Command
{
    protected $signature = 'isp:production-audit {--skip-tests : Do not run PHPUnit}';

    protected $description = 'Pre-production checklist: config, env hints, orphan widgets, optional tests.';

    public function handle(): int
    {
        $this->info('=== ISP Platform production audit ===');
        $issues = 0;

        $storageIssues = EnsureStorageWritable::findIssues();

        if ($storageIssues === []) {
            $this->line('[ok] storage paths writable');
        } else {
            foreach ($storageIssues as $storageIssue) {
                $this->error('[X] '.$storageIssue);
            }

            $this->warn('    Fix: sudo scripts/fix-storage-permissions.sh');
            $issues++;
        }

        if (config('app.debug')) {
            $this->warn('[!] APP_DEBUG=true — set false in production');
            $issues++;
        } else {
            $this->line('[ok] APP_DEBUG is off');
        }

        if (! config('app.key')) {
            $this->error('[X] APP_KEY missing');
            $issues++;
        }

        foreach (['APP_URL', 'DB_DATABASE'] as $key) {
            if (blank(env($key))) {
                $this->warn("[!] {$key} not set in .env");
                $issues++;
            }
        }

        if (! config('notifications.sms.enabled') && ! config('notifications.telegram.enabled')) {
            $this->warn('[!] SMS and Telegram both disabled — ops alerts may be silent');
        }

        if (app()->environment('production')) {
            foreach ([
                'support.webhook_secret' => 'SUPPORT_WEBHOOK_SECRET',
                'netflow.webhook_secret' => 'NETFLOW_WEBHOOK_SECRET',
                'optical.webhook_secret' => 'OPTICAL_WEBHOOK_SECRET',
            ] as $key => $env) {
                if (blank(config($key))) {
                    $this->warn("[!] {$env} not set — related webhook rejects requests in production");
                    $issues++;
                }
            }
        }

        $widgetDir = app_path('Filament/Widgets');
        $orphans = [];
        foreach (File::glob($widgetDir.'/*.php') ?: [] as $file) {
            $class = 'App\\Filament\\Widgets\\'.basename($file, '.php');
            if (! class_exists($class)) {
                continue;
            }
            $allowed = in_array($class, DashboardPreferencesService::DEFAULT_WIDGETS, true);
            $referenced = $this->isClassReferenced($class);
            if (! $allowed && ! $referenced) {
                $orphans[] = $class;
            }
        }

        if ($orphans !== []) {
            $this->warn('[!] Widget classes not on dashboard & rarely referenced:');
            foreach ($orphans as $o) {
                $this->line('    - '.$o);
            }
        } else {
            $this->line('[ok] No obvious orphan widgets');
        }

        if (! $this->option('skip-tests')) {
            $this->info('Running test suite…');
            $exit = Artisan::call('test', [], $this->output);
            if ($exit !== 0) {
                $issues++;
                $this->error('[X] Tests failed');
            } else {
                $this->line('[ok] Tests passed');
            }
        }

        $this->newLine();
        if ($issues === 0) {
            $this->info('Audit complete — no critical issues reported.');

            return self::SUCCESS;
        }

        $this->warn("Audit complete — {$issues} issue group(s) need attention.");

        return self::FAILURE;
    }

    private function isClassReferenced(string $class): bool
    {
        $short = class_basename($class);
        $paths = [
            app_path('Filament'),
            app_path('Providers'),
            resource_path('views'),
        ];

        foreach ($paths as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && str_contains((string) file_get_contents($file->getPathname()), $short)) {
                    return true;
                }
            }
        }

        return false;
    }
}
