<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PortalMarquee;
use App\Models\PortalNotice;
use App\Models\Reseller;
use App\Models\ResellerPackage;
use App\Models\Subzone;
use App\Models\Tenant;
use App\Models\Zone;
use App\Support\ResellerPortalPermission;
use App\Support\ResellerType;
use App\Support\TenantResolver;
use Database\Seeders\ShopProductSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class DemoSetupCommand extends Command
{
    private const DEMO_PORTAL_PASSWORD = 'demo123';

    protected $signature = 'isp:demo-setup
                            {--tenant=1 : Tenant id}
                            {--fresh : Run migrate:fresh (destructive)}';

    protected $description = 'Prepare a full demo site: landing, portal, reseller, shop, pay bill, admin';

    public function handle(): int
    {
        $tenantId = max(1, (int) $this->option('tenant'));

        if ($this->option('fresh')) {
            if (is_file(storage_path('.production-live')) || app()->environment('production')) {
                $this->error('migrate:fresh is blocked on production. Use staging or remove --fresh.');

                return self::FAILURE;
            }
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

        $this->seedWebsitePackages($tenantId);
        $reseller = $this->seedDemoReseller($tenantId);
        $this->seedSampleClients($tenantId, $reseller);
        $this->seedDemoInvoices($tenantId);
        $this->seedDemoShop($tenantId);
        $this->seedPortalContent($tenantId);

        TenantResolver::clearFake();

        $base = rtrim((string) config('app.url'), '/');

        $this->newLine();
        $this->info('Full demo website ready (fake data only):');
        $this->table(
            ['Page', 'URL'],
            [
                ['Landing', $base.'/'],
                ['Sign in hub', $base.'/login'],
                ['Customer portal', $base.'/portal/login'],
                ['Reseller portal', $base.'/reseller/login'],
                ['Pay bill', $base.'/pay'],
                ['Shop', $base.'/shop'],
                ['Admin', $base.'/admin'],
            ],
        );

        $this->newLine();
        $this->table(
            ['Role', 'Login', 'Password'],
            [
                ['Customer', 'DEMO-001', self::DEMO_PORTAL_PASSWORD],
                ['Reseller', 'DEMO-RSL', self::DEMO_PORTAL_PASSWORD],
                ['Admin', config('isp.admin_email'), '(ISP_ADMIN_PASSWORD from .env)'],
                ['Pay bill', 'Client code DEMO-001', '—'],
            ],
        );

        $this->newLine();
        $this->warn('Ensure .env has:');
        $this->line('ISP_DEMO_MODE=true');
        $this->line('ISP_LANDING_DOMAIN=demo.anetbd.com');
        $this->line('PORTAL_ENABLED=true');
        $this->line('RESELLER_PORTAL_ENABLED=true');
        $this->line('INVENTORY_SHOP_ENABLED=true');
        $this->newLine();
        $this->info('Then: php artisan config:clear');

        return self::SUCCESS;
    }

    private function seedWebsitePackages(int $tenantId): void
    {
        $routerId = (int) (MikrotikServer::query()->where('tenant_id', $tenantId)->value('id') ?? 0);

        Package::query()
            ->where('tenant_id', $tenantId)
            ->where('name', 'Demo 10 Mbps')
            ->update(['show_on_website' => true]);

        $catalog = [
            ['name' => 'Home 15 Mbps', 'download_mbps' => 15, 'upload_mbps' => 15, 'price_monthly' => 750],
            ['name' => 'Premium 25 Mbps', 'download_mbps' => 25, 'upload_mbps' => 25, 'price_monthly' => 1100],
            ['name' => 'Business 50 Mbps', 'download_mbps' => 50, 'upload_mbps' => 50, 'price_monthly' => 2000],
        ];

        foreach ($catalog as $row) {
            Package::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'name' => $row['name']],
                [
                    'mikrotik_server_id' => $routerId ?: null,
                    'type' => 'residential',
                    'download_mbps' => $row['download_mbps'],
                    'upload_mbps' => $row['upload_mbps'],
                    'price_monthly' => $row['price_monthly'],
                    'setup_fee' => 0,
                    'vat_percent' => 0,
                    'billing_cycle_days' => 30,
                    'is_active' => true,
                    'show_on_website' => true,
                ],
            );
        }

        $this->info('Landing page packages seeded.');
    }

    private function seedDemoReseller(int $tenantId): Reseller
    {
        $reseller = Reseller::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'DEMO-RSL'],
            [
                'name' => 'Demo Franchise Partner',
                'portal_login' => 'demo-reseller',
                'email' => 'demo-reseller@demo.anetbd.com',
                'phone' => '01711000999',
                'franchise_type' => ResellerType::FRANCHISE,
                'commission_type' => 'percent',
                'commission_value' => 12,
                'wallet_balance' => 25000,
                'is_active' => true,
                'api_access_enabled' => true,
                'white_label_enabled' => true,
                'own_integrations_enabled' => false,
                'portal_password' => Hash::make(self::DEMO_PORTAL_PASSWORD),
                'portal_permissions' => ResellerPortalPermission::defaultsFor(ResellerType::FRANCHISE),
            ],
        );

        if (! filled($reseller->portal_password)) {
            $reseller->forceFill(['portal_password' => Hash::make(self::DEMO_PORTAL_PASSWORD)])->save();
        }

        $packageIds = Package::query()->where('tenant_id', $tenantId)->where('is_active', true)->pluck('id');
        foreach ($packageIds as $packageId) {
            $pkg = Package::query()->find($packageId);
            ResellerPackage::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'reseller_id' => $reseller->id,
                    'package_id' => $packageId,
                ],
                [
                    'selling_price' => (float) ($pkg?->price_monthly ?? 500),
                    'wholesale_price' => max(300, (float) ($pkg?->price_monthly ?? 500) * 0.7),
                    'is_active' => true,
                ],
            );
        }

        $this->info('Demo reseller DEMO-RSL ready.');

        return $reseller;
    }

    private function seedSampleClients(int $tenantId, Reseller $reseller): void
    {
        $areaId = Area::query()->where('tenant_id', $tenantId)->value('id');
        $zoneId = Zone::query()->where('tenant_id', $tenantId)->value('id');
        $subzoneId = Subzone::query()->where('tenant_id', $tenantId)->value('id');
        $routerId = MikrotikServer::query()->where('tenant_id', $tenantId)->value('id');
        $packageIds = Package::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('price_monthly')
            ->pluck('id')
            ->all();

        $samples = [
            ['customer_code' => 'DEMO-001', 'name' => 'Karim Ahmed', 'phone' => '01711000001', 'status' => 'active'],
            ['customer_code' => 'DEMO-002', 'name' => 'Rahim Uddin', 'phone' => '01711000002', 'status' => 'active'],
            ['customer_code' => 'DEMO-003', 'name' => 'Sumaiya Begum', 'phone' => '01711000003', 'status' => 'active'],
            ['customer_code' => 'DEMO-004', 'name' => 'Fatima Khatun', 'phone' => '01711000004', 'status' => 'active'],
            ['customer_code' => 'DEMO-005', 'name' => 'Abdul Jabbar', 'phone' => '01711000005', 'status' => 'suspended'],
            ['customer_code' => 'DEMO-006', 'name' => 'Nusrat Jahan', 'phone' => '01711000006', 'status' => 'active'],
            ['customer_code' => 'DEMO-007', 'name' => 'Shafiqul Islam', 'phone' => '01711000007', 'status' => 'inactive'],
            ['customer_code' => 'DEMO-008', 'name' => 'Mizanur Rahman', 'phone' => '01711000008', 'status' => 'active'],
            ['customer_code' => 'DEMO-009', 'name' => 'Rokeya Sultana', 'phone' => '01711000009', 'status' => 'active'],
            ['customer_code' => 'DEMO-010', 'name' => 'Tanvir Hasan', 'phone' => '01711000010', 'status' => 'suspended'],
            ['customer_code' => 'DEMO-011', 'name' => 'Jannatul Ferdous', 'phone' => '01711000011', 'status' => 'active'],
            ['customer_code' => 'DEMO-012', 'name' => 'Delwar Hossain', 'phone' => '01711000012', 'status' => 'active'],
            ['customer_code' => 'DEMO-013', 'name' => 'Shahana Parvin', 'phone' => '01711000013', 'status' => 'inactive'],
            ['customer_code' => 'DEMO-014', 'name' => 'Imran Khan', 'phone' => '01711000014', 'status' => 'active'],
            ['customer_code' => 'DEMO-015', 'name' => 'Salma Akter', 'phone' => '01711000015', 'status' => 'active'],
        ];

        $portalHash = Hash::make(self::DEMO_PORTAL_PASSWORD);
        $created = 0;
        $updated = 0;

        foreach ($samples as $index => $row) {
            $packageId = $packageIds[$index % max(1, count($packageIds))] ?? null;
            $pkg = $packageId ? Package::query()->find($packageId) : null;
            $monthly = (float) ($pkg?->price_monthly ?? 500);

            $attrs = [
                'tenant_id' => $tenantId,
                'customer_code' => $row['customer_code'],
                'name' => $row['name'],
                'phone' => $row['phone'],
                'email' => strtolower(str_replace(' ', '.', $row['name'])).'@demo.anetbd.local',
                'status' => $row['status'],
                'billing_day' => ($index % 28) + 1,
                'area_id' => $areaId,
                'zone_id' => $zoneId,
                'subzone_id' => $subzoneId,
                'package_id' => $packageId,
                'mikrotik_server_id' => $routerId,
                'mikrotik_secret_name' => $row['phone'],
                'mikrotik_ppp_password' => 'demo-ppp-'.($index + 1),
                'portal_password' => $portalHash,
                'reseller_id' => $index < 8 ? $reseller->id : null,
                'address' => 'Demo Address, Dhaka',
                'joined_at' => now()->subMonths(3)->toDateString(),
                'service_expires_at' => now()->addDays(20)->toDateString(),
                'account_balance' => $index % 4 === 0 ? $monthly : 0,
                'credit_limit' => 2000,
                'billing_mode' => 'postpaid',
                'grace_period_days' => 7,
                'network_access_state' => $row['status'] === 'suspended' ? 'suspended' : 'active',
                'notes' => 'Demo subscriber — not a real customer',
            ];

            $existing = Customer::query()->where('customer_code', $row['customer_code'])->first();
            if ($existing !== null) {
                if (blank($existing->portal_password) || blank($existing->package_id)) {
                    $existing->updateTrusted($attrs);
                    $updated++;
                }

                continue;
            }

            Customer::createTrusted($attrs);
            $created++;
        }

        $this->info("Demo subscribers ready ({$created} new, {$updated} updated, ".count($samples).' total).');
    }

    private function seedDemoInvoices(int $tenantId): void
    {
        $customers = Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_code', 'like', 'DEMO-%')
            ->orderBy('id')
            ->get();

        $invoiceCount = 0;
        $paymentCount = 0;

        foreach ($customers as $index => $customer) {
            if ($customer->invoices()->exists()) {
                continue;
            }

            $monthly = (float) ($customer->package?->price_monthly ?? 500);
            $paid = $index % 3 === 0;

            $invoice = Invoice::createTrusted([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'issue_date' => now()->subDays(5)->toDateString(),
                'due_date' => now()->addDays(10)->toDateString(),
                'period_start' => now()->subMonth()->startOfMonth()->toDateString(),
                'period_end' => now()->subMonth()->endOfMonth()->toDateString(),
                'subtotal' => $monthly,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total' => $monthly,
                'amount_paid' => $paid ? $monthly : 0,
                'status' => $paid ? 'paid' : 'open',
            ]);
            $invoiceCount++;

            if ($paid) {
                Payment::createTrusted([
                    'tenant_id' => $tenantId,
                    'customer_id' => $customer->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $monthly,
                    'method' => 'cash',
                    'status' => 'completed',
                    'paid_at' => now()->subDays(2),
                    'receipt_number' => 'DEMO-RCP-'.$customer->customer_code,
                ]);
                $paymentCount++;
            }
        }

        $this->info("Demo invoices: {$invoiceCount}, payments: {$paymentCount}.");
    }

    private function seedDemoShop(int $tenantId): void
    {
        config(['inventory.default_tenant_id' => $tenantId]);
        (new ShopProductSeeder)->run();
        $this->info('Demo shop products seeded.');
    }

    private function seedPortalContent(int $tenantId): void
    {
        PortalNotice::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'title' => 'ডেমো সাইট'],
            [
                'body' => 'এটি শুধু ডেমো — কোনো আসল গ্রাহক বা পেমেন্ট নেই। সব ফিচার explore করতে পারেন।',
                'sort' => 1,
                'is_active' => true,
                'show_on_landing' => true,
                'show_on_portal' => true,
            ],
        );

        PortalMarquee::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'text' => 'Demo mode — packages, portal, reseller & shop with fake data only'],
            [
                'url' => null,
                'sort' => 1,
                'is_active' => true,
                'show_on_landing' => true,
                'show_on_portal' => true,
            ],
        );

        $this->info('Landing & portal notices seeded.');
    }
}
