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

        (new PermissionSeeder)->run();

        try {
            app(\App\Services\Saas\OperatorProvisioningService::class)->ensureStaffRole();
            $this->info('ISP staff (Admin) role synced with limited permissions.');
        } catch (\Throwable $e) {
            $this->warn('Staff role sync skipped: '.$e->getMessage());
        }

        try {
            app(\App\Services\Saas\OperatorProvisioningService::class)->ensureRoles();
            $this->info('Sold ISP (Operator) synced — all permissions except Sell ISP.');
        } catch (\Throwable $e) {
            $this->warn('Operator permission sync skipped: '.$e->getMessage());
        }

        if (! User::query()->role('Super Admin')->exists()) {
            $this->info('First deploy — seeding roles and super admin…');
            (new RoleSeeder)->run();
            (new SuperAdminSeeder)->run();
            $this->info('Default login: rohan9222@gmail.com / rohan9222@gmail.com');
        }

        $skipDemo = (string) \App\Models\MainSiteData::getValue('skip_demo_seed', '') === '1';
        if (! $skipDemo && \App\Models\CustomersInfo::query()->count() === 0) {
            $this->info('Seeding demo MikroTik / OLT / clients…');
            (new \Database\Seeders\DemoReadySeeder)->run();
        }

        $processStats = (new AutomaticProcessSeeder)->syncOnDeploy();
        $this->info(sprintf(
            'Automatic processes synced (created: %d, updated: %d).',
            $processStats['created'],
            $processStats['updated'],
        ));

        $smsCreated = app(SmsTemplateCatalogService::class)->syncAllTenants();
        $this->info("SMS templates synced (created: {$smsCreated}).");

        $this->ensureAnetbdBrand();

        Artisan::call('storage:link', ['--force' => true]);
        Artisan::call('view:cache');

        $this->info('Post-deploy complete.');

        return self::SUCCESS;
    }

    private function ensureAnetbdBrand(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('main_site_data')) {
            return;
        }

        $legacy = ['sam online', 'samonline', 'code pagol', 'codepagol', 'isp billing', 'laravel'];
        $map = [
            'site_name' => 'Anetbd',
            'portal_name' => 'Anetbd',
            'site_title' => 'Anetbd',
        ];

        foreach ($map as $key => $value) {
            $current = \App\Models\MainSiteData::getValue($key, '');
            $normalized = is_string($current) ? strtolower(trim($current)) : '';
            if ($normalized === '' || in_array($normalized, $legacy, true)) {
                \App\Models\MainSiteData::setValue($key, $value);
            }
        }

        $this->restoreClassicLanding();

        $this->info('Brand and classic landing set to Anetbd.');
    }

    private function restoreClassicLanding(): void
    {
        $classicSlides = [
            ['image' => 'images/slide/img0.jpg', 'caption' => ''],
            ['image' => 'images/slide/img1.jpg', 'caption' => ''],
            ['image' => 'images/slide/img2.jpg', 'caption' => ''],
        ];

        $heroTitle = (string) \App\Models\MainSiteData::getValue('hero_title', '');
        $aboutBody = (string) \App\Models\MainSiteData::getValue('about_body', '');
        $isMarketingCopy = $heroTitle === ''
            || $heroTitle === 'Faster. Reliable. Always On.'
            || str_contains($aboutBody, 'Clean ISP operations');

        if ($isMarketingCopy) {
            \App\Models\MainSiteData::setValue('hero_title', 'We are always Faster & Reliable');
            \App\Models\MainSiteData::setValue('hero_subtitle', '');
            \App\Models\MainSiteData::setValue('about_body', '');
            \App\Models\MainSiteData::setValue('hero_slides', $classicSlides);
        }

        $slides = \App\Models\MainSiteData::getValue('hero_slides', []);
        $slideImages = is_array($slides)
            ? collect($slides)->map(fn ($slide) => (string) ($slide['image'] ?? ''))->implode(' ')
            : '';
        if (! is_array($slides) || count($slides) === 0 || ! str_contains($slideImages, 'images/slide/img0.jpg')) {
            \App\Models\MainSiteData::setValue('hero_slides', $classicSlides);
        }

        $theme = (string) \App\Models\MainSiteData::getValue('theme_mode', '');
        if ($theme === '' || $theme === 'dark') {
            \App\Models\MainSiteData::setValue('theme_mode', 'light');
        }

        \Illuminate\Support\Facades\Cache::forget('main_site_data_active');
    }
}
