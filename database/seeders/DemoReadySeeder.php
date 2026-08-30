<?php

namespace Database\Seeders;

use App\Models\BillingInfo;
use App\Models\CollectionSummary;
use App\Models\CustomersInfo;
use App\Models\MainSiteData;
use App\Models\OfficialInfo;
use App\Models\Olt;
use App\Models\OltPort;
use App\Models\PackageList;
use App\Models\PPPSecrets;
use App\Models\RouterList;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo MikroTik + OLT + packages + clients for ready-to-play dashboards.
 * Safe to re-run: skips when demo customers already exist.
 */
class DemoReadySeeder extends Seeder
{
    public function run(): void
    {
        if (CustomersInfo::query()->where('customer_unique_id', 'like', 'DEMO-%')->exists()) {
            $this->command?->info('Demo customers already present — skipping DemoReadySeeder.');

            return;
        }

        DB::transaction(function () {
            $this->seedSiteCopy();
            $routers = $this->seedRouters();
            $packages = $this->seedPackages($routers[0]->router_name);
            $this->seedOlts();
            $this->seedCustomers($routers, $packages);
        });

        $this->command?->info('Demo MikroTik, OLT, packages, and clients ready.');
    }

    private function seedSiteCopy(): void
    {
        MainSiteData::setValue('site_name', 'Anetbd');
        MainSiteData::setValue('site_title', 'Anetbd — Faster & Reliable Internet');
        MainSiteData::setValue('hero_title', 'We are always Faster & Reliable');
        MainSiteData::setValue('hero_subtitle', '');
        MainSiteData::setValue('hero_slides', [
            ['image' => 'images/slide/img0.jpg', 'caption' => ''],
            ['image' => 'images/slide/img1.jpg', 'caption' => ''],
            ['image' => 'images/slide/img2.jpg', 'caption' => ''],
        ]);
        MainSiteData::setValue('theme_mode', 'light');
        MainSiteData::setValue('about_title', 'Welcome to Anetbd');
        MainSiteData::setValue('about_body', '');
        MainSiteData::setValue('packages_section_title', 'Internet Packages');
        MainSiteData::setValue('packages_section_subtitle', 'Pick a plan that fits your home or office.');
        MainSiteData::setValue('services', [
            ['icon' => 'bi bi-house-fill', 'title' => 'Home Internet', 'description' => 'High-speed broadband internet for your home. Unlimited data, 24/7 uptime.'],
            ['icon' => 'bi bi-building-fill-check', 'title' => 'Corporate Internet', 'description' => 'Dedicated business-grade connectivity with SLA guarantees and priority support.'],
            ['icon' => 'bi bi-hdd-network-fill', 'title' => 'Data Connectivity', 'description' => 'Fiber optic point-to-point links for enterprise and campus connectivity needs.'],
        ]);
    }

    /**
     * @return list<RouterList>
     */
    private function seedRouters(): array
    {
        $rows = [
            [
                'router_name' => 'Core-MT-01',
                'ip_address' => '10.10.10.1',
                'username' => 'admin',
                'password' => 'demo_secret',
                'ssh_port' => 22,
                'api_port' => 8728,
                'action' => 'connected',
            ],
            [
                'router_name' => 'POP-South-02',
                'ip_address' => '10.10.20.1',
                'username' => 'admin',
                'password' => 'demo_secret',
                'ssh_port' => 22,
                'api_port' => 8728,
                'action' => 'connected',
            ],
        ];

        $out = [];
        foreach ($rows as $row) {
            $out[] = RouterList::query()->updateOrCreate(
                ['router_name' => $row['router_name']],
                $row
            );
        }

        return $out;
    }

    /**
     * @return list<PackageList>
     */
    private function seedPackages(string $routerName): array
    {
        $defs = [
            ['package' => 'Home-10Mbps', 'plan_label' => 'Home 10', 'speed' => '10 Mbps', 'price' => 500, 'sort_order' => 10, 'is_featured' => false],
            ['package' => 'Home-20Mbps', 'plan_label' => 'Home 20', 'speed' => '20 Mbps', 'price' => 700, 'sort_order' => 20, 'is_featured' => true],
            ['package' => 'Office-50Mbps', 'plan_label' => 'Office 50', 'speed' => '50 Mbps', 'price' => 1500, 'sort_order' => 30, 'is_featured' => false],
        ];

        $out = [];
        foreach ($defs as $def) {
            $out[] = PackageList::query()->updateOrCreate(
                ['package' => $def['package']],
                array_merge($def, [
                    'description' => $def['speed'].' unlimited (demo)',
                    'show_on_site' => true,
                    'router_name' => $routerName,
                    'push_to_mikrotik' => false,
                    'features' => ['Unlimited', 'Public IP optional', '24/7 support'],
                ])
            );
        }

        return $out;
    }

    private function seedOlts(): void
    {
        $olt = Olt::query()->updateOrCreate(
            ['name' => 'OLT-Main-BDCOM'],
            [
                'vendor' => 'BDCOM',
                'olt_driver' => 'bdcom',
                'model' => 'P3310C',
                'location' => 'Main POP — Dhaka',
                'management_ip' => '10.20.30.1',
                'snmp_host' => '10.20.30.1',
                'snmp_port' => 161,
                'snmp_community' => 'public',
                'snmp_version' => 'v2c',
                'ssh_port' => 22,
                'ssh_username' => 'admin',
                'status' => 'active',
                'olt_health' => ['cpu' => 18, 'temp_c' => 42, 'status' => 'ok'],
                'notes' => 'Demo OLT for dashboard / optical checks',
                'last_health_polled_at' => now(),
            ]
        );

        Olt::query()->updateOrCreate(
            ['name' => 'OLT-East-Vsol'],
            [
                'vendor' => 'VSOL',
                'olt_driver' => 'vsol',
                'model' => 'V1600D',
                'location' => 'East POP',
                'management_ip' => '10.20.40.1',
                'snmp_host' => '10.20.40.1',
                'snmp_port' => 161,
                'snmp_community' => 'public',
                'snmp_version' => 'v2c',
                'status' => 'active',
                'olt_health' => ['cpu' => 22, 'temp_c' => 45, 'status' => 'ok'],
                'notes' => 'Demo secondary OLT',
                'last_health_polled_at' => now(),
            ]
        );

        foreach ([1, 2, 3, 4] as $pon) {
            OltPort::query()->updateOrCreate(
                ['olt_id' => $olt->id, 'card_index' => 0, 'pon_index' => $pon],
                [
                    'label' => "PON-0/{$pon}",
                    'admin_status' => 'enabled',
                    'oper_status' => $pon <= 2 ? 'up' : 'unknown',
                    'utilization_percent' => $pon * 12.5,
                    'last_polled_at' => now(),
                ]
            );
        }
    }

    /**
     * @param  list<RouterList>  $routers
     * @param  list<PackageList>  $packages
     */
    private function seedCustomers(array $routers, array $packages): void
    {
        $demos = [
            ['id' => 'DEMO-1001', 'name' => 'Karim Hossain', 'mobile' => '01711001001', 'status' => 'active', 'due' => 0, 'paid' => 700, 'pkg' => 1, 'router' => 0, 'day' => 5],
            ['id' => 'DEMO-1002', 'name' => 'Nusrat Jahan', 'mobile' => '01711001002', 'status' => 'active', 'due' => 700, 'paid' => 0, 'pkg' => 1, 'router' => 0, 'day' => 10],
            ['id' => 'DEMO-1003', 'name' => 'Rahim Uddin', 'mobile' => '01711001003', 'status' => 'disable', 'due' => 1500, 'paid' => 0, 'pkg' => 0, 'router' => 0, 'day' => 1],
            ['id' => 'DEMO-1004', 'name' => 'Office Twin Tech', 'mobile' => '01711001004', 'status' => 'active', 'due' => 0, 'paid' => 1500, 'pkg' => 2, 'router' => 1, 'day' => 15],
            ['id' => 'DEMO-1005', 'name' => 'Salma Akter', 'mobile' => '01711001005', 'status' => 'pending', 'due' => 500, 'paid' => 0, 'pkg' => 0, 'router' => 1, 'day' => 20],
            ['id' => 'DEMO-1006', 'name' => 'Faruk Ahmed', 'mobile' => '01711001006', 'status' => 'active', 'due' => 500, 'paid' => 0, 'pkg' => 0, 'router' => 0, 'day' => 24],
            ['id' => 'DEMO-1007', 'name' => 'Green Cafe BD', 'mobile' => '01711001007', 'status' => 'active', 'due' => 0, 'paid' => 1500, 'pkg' => 2, 'router' => 1, 'day' => 8],
            ['id' => 'DEMO-1008', 'name' => 'Mitu Chowdhury', 'mobile' => '01711001008', 'status' => 'inactive', 'due' => 1400, 'paid' => 0, 'pkg' => 1, 'router' => 0, 'day' => 12],
        ];

        foreach ($demos as $i => $demo) {
            $router = $routers[$demo['router']];
            $package = $packages[$demo['pkg']];
            $username = strtolower(str_replace('-', '', $demo['id']));

            $ppp = PPPSecrets::query()->updateOrCreate(
                ['username' => $username],
                [
                    'password' => 'demo1234',
                    'service' => 'pppoe',
                    'profile' => $package->package,
                    'router_name' => $router->router_name,
                    'status' => $demo['status'] === 'active' ? 'active' : 'disable',
                    'comment' => 'Demo client',
                ]
            );

            $customer = CustomersInfo::query()->updateOrCreate(
                ['customer_unique_id' => $demo['id']],
                [
                    'customer_name' => $demo['name'],
                    'mobile' => $demo['mobile'],
                    'email' => strtolower($username).'@demo.anetbd.com',
                    'address' => 'Demo Address, Dhaka',
                    'status' => $demo['status'],
                    'disable_count' => $demo['status'] === 'disable' ? 1 : 0,
                    'ppp_user_id' => $ppp->id,
                    'package_id' => $package->id,
                    'connection_date' => now()->subMonths(2)->toDateString(),
                ]
            );

            OfficialInfo::query()->updateOrCreate(
                ['customer_office_unique_id' => $customer->customer_unique_id],
                ['continue_bill' => true]
            );

            $rent = (float) $package->price;
            BillingInfo::query()->updateOrCreate(
                ['customer_bill_unique_id' => $customer->customer_unique_id],
                [
                    'monthly_rent' => $rent,
                    'additional_charge' => 0,
                    'vat' => 0,
                    'discount' => 0,
                    'advance' => 0,
                    'previous_due' => max(0, $demo['due'] - $rent),
                    'due_amount' => $demo['due'],
                    'paid_amount' => $demo['paid'],
                    'total_amount' => max($rent, $demo['due']),
                    'auto_disable' => true,
                    'auto_disable_date' => Carbon::today()->day($demo['day'])->toDateString(),
                    'auto_disable_month' => 1,
                    'billing_day' => $demo['day'],
                    'grace_period_days' => 3,
                    'billing_type' => 'postpaid',
                    'paid_date' => $demo['paid'] > 0 ? now()->subDays(2) : null,
                ]
            );

            if ($demo['paid'] > 0) {
                CollectionSummary::query()->firstOrCreate(
                    [
                        'customer_collection_unique_id' => $customer->customer_unique_id,
                        'transaction_id' => 'DEMO-COL-'.$demo['id'],
                    ],
                    [
                        'collection_date' => now()->subDays(2),
                        'collection_amount' => $demo['paid'],
                        'collected_by' => 'Demo Seeder',
                        'payment_type' => 'cash',
                        'payment_method' => 'cash',
                        'payment_status' => 'paid',
                        'invoice_no' => CollectionSummary::nextInvoiceNo(),
                        'bill_month' => now()->format('F Y'),
                    ]
                );
            }
        }
    }
}
