<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Sms\SmsTemplateCatalogService;
use Database\Seeders\AutomaticProcessSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class PostDeploy extends Command
{
    protected $signature = 'cpagol:post-deploy';

    protected $description = 'Run migrations, sync deploy defaults, and warm caches after deploy';

    public function handle(): int
    {
        $this->info('Running post-deploy tasks…');

        Artisan::call('migrate', ['--force' => true]);
        $this->line(trim(Artisan::output()));

        if (! User::query()->role('Super Admin')->exists()) {
            $this->info('First deploy — seeding roles and super admin…');
            (new PermissionSeeder)->run();
            (new RoleSeeder)->run();
            (new SuperAdminSeeder)->run();
            $this->info('Default login: rohan9222@gmail.com / rohan9222@gmail.com');
        }

        if (\App\Models\CustomersInfo::query()->count() === 0) {
            $this->info('Seeding demo MikroTik / OLT / clients…');
            (new \Database\Seeders\DemoReadySeeder)->run();
        }

        $processStats = (new AutomaticProcessSeeder)->syncOnDeploy();
        $this->info(sprintf(
            'Automatic processes synced (created: %d, updated: %d).',
            $processStats['created'],
            $processStats['updated'],
        ));

        $smsCreated = app(SmsTemplateCatalogService::class)->syncMissing();
        $this->info("SMS templates synced (created: {$smsCreated}).");

        Artisan::call('storage:link', ['--force' => true]);
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');

        $this->info('Post-deploy complete.');

        return self::SUCCESS;
    }
}
