<?php

namespace App\Console\Commands;

use App\Models\SmsTemplate;
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
                            {--skip-migrate : Skip migrate (when bootstrap already ran it)}';

    protected $description = 'After GitHub deploy: sync built-in automatic processes, SMS templates, roles';

    public function handle(AutomaticProcessSeeder $processSeeder, SmsTemplateService $smsTemplates): int
    {
        if (! $this->option('skip-migrate')) {
            $this->line('Running migrations...');
            Artisan::call('migrate', ['--force' => true]);
        }

        if (Schema::hasTable('permissions')) {
            Artisan::call('db:seed', ['--class' => IspPermissionsSeeder::class, '--force' => true]);
            Artisan::call('db:seed', ['--class' => IspRolesSeeder::class, '--force' => true]);
            $this->line('Permissions & roles synced.');
        }

        if (Schema::hasTable('automatic_processes')) {
            $stats = $processSeeder->syncOnDeploy();
            $this->info(sprintf(
                'Automatic processes: %d created, %d updated (your enabled/interval settings kept).',
                $stats['created'],
                $stats['updated'],
            ));
        }

        if (Schema::hasTable('sms_templates')) {
            $smsCount = SmsTemplate::count() === 0
                ? $smsTemplates->seedDefaults()
                : $smsTemplates->syncMissingDefaults();
            $this->info("SMS templates: {$smsCount} added from catalog.");
        }

        Artisan::call('isp:bootstrap-admin', [], $this->output);

        $this->info('Post-deploy sync complete.');

        return self::SUCCESS;
    }
}
