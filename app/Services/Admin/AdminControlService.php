<?php

namespace App\Services\Admin;

use App\Models\AutomaticProcess;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Sms\SmsTemplateCatalogService;
use Database\Seeders\AutomaticProcessSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Admin control plane — maintenance, health, and module shortcuts (no code edits needed).
 */
final class AdminControlService
{
    public function payload(): array
    {
        return [
            'site' => $this->siteIdentity(),
            'health' => $this->health(),
            'modules' => $this->adminModules(),
            'last_maintenance' => Cache::get('admin.last_maintenance'),
        ];
    }

    /**
     * @return array{ok: bool, output: string, steps: list<array<string, mixed>>}
     */
    public function runFullMaintenance(): array
    {
        $steps = [];
        $lines = [];

        try {
            Artisan::call('migrate', ['--force' => true]);
            $migrateOut = trim(Artisan::output());
            $steps[] = ['label' => 'Database migrate', 'ok' => true, 'detail' => $migrateOut ?: 'Up to date'];
            $lines[] = $migrateOut ?: 'Migrations up to date.';
        } catch (\Throwable $e) {
            $steps[] = ['label' => 'Database migrate', 'ok' => false, 'detail' => $e->getMessage()];
            $lines[] = 'Migrate failed: '.$e->getMessage();
        }

        try {
            $stats = (new AutomaticProcessSeeder)->syncOnDeploy();
            $detail = "created {$stats['created']}, updated {$stats['updated']}";
            $steps[] = ['label' => 'Automatic processes', 'ok' => true, 'detail' => $detail];
            $lines[] = 'Automatic processes synced ('.$detail.').';
        } catch (\Throwable $e) {
            $steps[] = ['label' => 'Automatic processes', 'ok' => false, 'detail' => $e->getMessage()];
            $lines[] = 'Process sync failed: '.$e->getMessage();
        }

        try {
            $created = app(SmsTemplateCatalogService::class)->syncMissing();
            $steps[] = ['label' => 'SMS templates', 'ok' => true, 'detail' => "{$created} created"];
            $lines[] = "SMS templates synced ({$created} created).";
        } catch (\Throwable $e) {
            $steps[] = ['label' => 'SMS templates', 'ok' => false, 'detail' => $e->getMessage()];
            $lines[] = 'SMS sync failed: '.$e->getMessage();
        }

        foreach (['storage:link' => ['--force' => true], 'config:cache' => [], 'route:cache' => [], 'view:cache' => []] as $cmd => $args) {
            try {
                Artisan::call($cmd, $args);
                $steps[] = ['label' => $cmd, 'ok' => true, 'detail' => 'OK'];
                $lines[] = $cmd.' OK';
            } catch (\Throwable $e) {
                $steps[] = ['label' => $cmd, 'ok' => false, 'detail' => $e->getMessage()];
                $lines[] = $cmd.' failed: '.$e->getMessage();
            }
        }

        $ok = collect($steps)->every(fn (array $s) => $s['ok']);
        $record = [
            'at' => now()->toIso8601String(),
            'by' => auth()->user()?->name,
            'ok' => $ok,
            'steps' => $steps,
            'output' => implode("\n", $lines),
        ];
        Cache::forever('admin.last_maintenance', $record);

        return [
            'ok' => $ok,
            'output' => $record['output'],
            'steps' => $steps,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function siteIdentity(): array
    {
        return [
            'name' => site_brand(),
            'title' => siteUrlSettings('site_title') ?: site_brand(),
            'url' => config('app.url'),
            'logo' => site_image(siteUrlSettings('site_logo'), 'images/favicon.png'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function health(): array
    {
        $dbOk = false;
        $pendingMigrations = 0;

        try {
            DB::connection()->getPdo();
            $dbOk = true;
            $pendingMigrations = $this->pendingMigrationCount();
        } catch (\Throwable) {
            $dbOk = false;
        }

        return [
            'database' => $dbOk,
            'pending_migrations' => $pendingMigrations,
            'storage_writable' => is_writable(storage_path()),
            'storage_linked' => is_link(public_path('storage')) || is_dir(public_path('storage')),
            'staff_users' => User::query()->count(),
            'automatic_processes' => Schema::hasTable('automatic_processes')
                ? AutomaticProcess::query()->where('enabled', true)->count()
                : 0,
            'open_tickets' => Schema::hasTable('support_tickets')
                ? SupportTicket::query()->whereIn('status', ['open', 'in_progress'])->count()
                : 0,
            'app_env' => config('app.env'),
            'php' => PHP_VERSION,
        ];
    }

    private function pendingMigrationCount(): int
    {
        if (! Schema::hasTable('migrations')) {
            return 0;
        }

        $ran = DB::table('migrations')->pluck('migration')->all();
        $files = collect(File::glob(database_path('migrations/*.php')))
            ->map(fn (string $path) => pathinfo($path, PATHINFO_FILENAME))
            ->values();

        return $files->diff($ran)->count();
    }

    /**
     * @return list<array<string, string>>
     */
    private function adminModules(): array
    {
        return [
            ['label' => 'Site & branding', 'description' => 'Name, logo, theme, portal', 'route' => 'site-settings', 'icon' => 'bi-palette'],
            ['label' => 'Automatic processes', 'description' => 'Billing SMS, disable, OLT poll', 'route' => 'automatic-processes', 'icon' => 'bi-clock-history'],
            ['label' => 'SMS setup', 'description' => 'Templates & notices', 'route' => 'sms-setup', 'icon' => 'bi-envelope-check'],
            ['label' => 'Support tickets', 'description' => 'Open, assign, reply', 'route' => 'admin-tickets', 'icon' => 'bi-chat-left-text'],
            ['label' => 'Admin users', 'description' => 'Staff accounts', 'route' => 'admin-users', 'icon' => 'bi-people'],
            ['label' => 'Roles & permissions', 'description' => 'Who can do what', 'route' => 'admin-roles', 'icon' => 'bi-shield-lock'],
            ['label' => 'ISP modules', 'description' => 'All billing/NOC/HR hubs', 'route' => 'isp-os', 'icon' => 'bi-grid-3x3-gap'],
            ['label' => 'System logs', 'description' => 'App & activity logs', 'route' => 'admin.system-logs', 'icon' => 'bi-journal-text'],
        ];
    }
}
