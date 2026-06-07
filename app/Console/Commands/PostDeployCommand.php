<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\SmsTemplate;
use App\Models\Tenant;
use App\Support\DeployReady;
use App\Services\Sms\SmsTemplateService;
use Database\Seeders\AutomaticProcessSeeder;
use Database\Seeders\IspPermissionsSeeder;
use Database\Seeders\IspRolesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

final class PostDeployCommand extends Command
{
    protected $signature = 'isp:post-deploy
                            {--skip-migrate : Skip migrate (when bootstrap already ran it)}
                            {--fast : Quick path for container start — admin, defaults, SMS; skip automatic processes}
                            {--processes-only : Only sync built-in automatic processes (background after --fast)}';

    protected $description = 'After deploy: seed defaults, admin, SMS templates, and sync automatic processes';

    public function handle(AutomaticProcessSeeder $processSeeder, SmsTemplateService $smsTemplates): int
    {
        if ($this->option('processes-only')) {
            return $this->syncAutomaticProcesses($processSeeder);
        }

        if (! $this->option('skip-migrate')) {
            $this->line('Running migrations...');
            Artisan::call('migrate', ['--force' => true]);
        }

        $this->ensureDefaultTenant();
        $this->syncRolesAndPermissions();

        $settingsAdded = AppSetting::syncMissingDefaultsFromEnv();
        if ($settingsAdded > 0) {
            $this->info("App settings: {$settingsAdded} defaults seeded from .env.");
        }

        $this->syncSmsTemplates($smsTemplates);

        Artisan::call('isp:bootstrap-admin', [], $this->output);

        if (! $this->option('fast')) {
            $this->syncAutomaticProcesses($processSeeder);
        } else {
            $this->line('Automatic processes deferred (run isp:post-deploy --processes-only in background).');
        }

        $this->info('Post-deploy sync complete.');

        DeployReady::markReady();

        if ($this->option('fast') || ! $this->option('processes-only')) {
            Artisan::call('isp:warm-dashboard-caches', [], $this->output);
        }

        return self::SUCCESS;
    }

    private function ensureDefaultTenant(): void
    {
        if (! Schema::hasTable('tenants') || Tenant::query()->exists()) {
            return;
        }

        Tenant::query()->firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default ISP', 'is_active' => true],
        );

        $this->info('Default tenant ready.');
    }

    private function syncRolesAndPermissions(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        Artisan::call('db:seed', ['--class' => IspPermissionsSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => IspRolesSeeder::class, '--force' => true]);
        $this->line('Permissions & roles synced.');
    }

    private function syncSmsTemplates(SmsTemplateService $smsTemplates): void
    {
        if (! Schema::hasTable('sms_templates')) {
            return;
        }

        $smsCount = SmsTemplate::count() === 0
            ? $smsTemplates->seedDefaults()
            : $smsTemplates->syncMissingDefaults();

        $this->info("SMS templates: {$smsCount} added from catalog.");
    }

    private function syncAutomaticProcesses(AutomaticProcessSeeder $processSeeder): int
    {
        if (! Schema::hasTable('automatic_processes')) {
            return self::SUCCESS;
        }

        $stats = $processSeeder->syncOnDeploy();
        $this->info(sprintf(
            'Automatic processes: %d created, %d updated (your enabled/interval settings kept).',
            $stats['created'],
            $stats['updated'],
        ));

        return self::SUCCESS;
    }
}
