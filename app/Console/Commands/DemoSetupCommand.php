<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Tenant;
use App\Support\TenantResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class DemoSetupCommand extends Command
{
    protected $signature = 'isp:demo-setup
                            {--tenant=1 : Tenant id}
                            {--fresh : Run migrate:fresh (destructive)}';

    protected $description = 'Prepare a demo/training instance: seed admin, demo network, sample clients';

    public function handle(): int
    {
        $tenantId = max(1, (int) $this->option('tenant'));

        if ($this->option('fresh')) {
            if (! $this->confirm('migrate:fresh will DELETE all data. Continue?')) {
                return self::SUCCESS;
            }
            Artisan::call('migrate:fresh', ['--force' => true]);
            $this->line(Artisan::output());
        }

        Artisan::call('db:seed', ['--force' => true]);
        $this->info('Roles & admin user seeded.');

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Demo ISP', 'is_active' => true],
        );
        $tenantId = (int) $tenant->id;

        TenantResolver::fake($tenantId);

        Artisan::call('isp:seed-demo-network', [
            '--tenant' => $tenantId,
            '--force' => true,
        ]);
        $this->line(trim(Artisan::output()));

        $this->seedSampleClients($tenantId);

        $this->newLine();
        $this->warn('Add to .env for demo mode:');
        $this->line('ISP_DEMO_MODE=true');
        $this->line('APP_DEBUG=false   # use false on public demo URL');
        $this->line('ISP_DEPLOYMENT_MODE=saas');
        $this->line('ISP_LICENSE_ENFORCE=false');
        $this->newLine();
        $this->info('Admin login: '.config('app.url').'/admin');
        $this->line('Email: '.config('isp.admin_email'));
        $this->line('Password: (ISP_ADMIN_PASSWORD from .env)');
        $this->newLine();
        $this->info('Then: php artisan config:clear');

        return self::SUCCESS;
    }

    private function seedSampleClients(int $tenantId): void
    {
        $samples = [
            ['customer_code' => 'DEMO-001', 'name' => 'Karim Ahmed', 'phone' => '01711000001', 'status' => 'active'],
            ['customer_code' => 'DEMO-002', 'name' => 'Rahim Uddin', 'phone' => '01711000002', 'status' => 'active'],
            ['customer_code' => 'DEMO-003', 'name' => 'Sumaiya Begum', 'phone' => '01711000003', 'status' => 'active'],
        ];

        foreach ($samples as $row) {
            Customer::createTrusted(array_merge($row, [
                'tenant_id' => $tenantId,
                'billing_day' => 1,
            ]));
        }

        $this->info('Created '.count($samples).' demo subscribers (DEMO-001 …).');
    }
}
