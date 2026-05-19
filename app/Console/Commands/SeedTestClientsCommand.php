<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Support\CustomerStatus;
use App\Support\SubscriberType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SeedTestClientsCommand extends Command
{
    protected $signature = 'isp:seed-test-clients
                            {--count=10 : Number of test subscribers}
                            {--admin-password=habib@123 : Set admin@isp.local password}
                            {--force : Re-create if customer_code already exists}';

    protected $description = 'Seed ISP Digital–style test subscribers with full profile + meta (for Client Details testing)';

    /** @var list<array<string, mixed>> */
    private array $profiles = [
        ['name' => 'Md. Habibur Rahman', 'segment' => 'Residential'],
        ['name' => 'Fatema Begum', 'segment' => 'Residential'],
        ['name' => 'Karim Uddin', 'segment' => 'Residential'],
        ['name' => 'Ayesha Khatun', 'segment' => 'Residential'],
        ['name' => 'Rafiqul Islam', 'segment' => 'Commercial'],
        ['name' => 'Nusrat Jahan', 'segment' => 'Residential'],
        ['name' => 'Shahidul Alam', 'segment' => 'Corporate'],
        ['name' => 'Mina Akter', 'segment' => 'Residential'],
        ['name' => 'Tanvir Hossain', 'segment' => 'Gaming'],
        ['name' => 'Salma Parvin', 'segment' => 'Residential'],
    ];

    public function handle(): int
    {
        $count = max(1, min(50, (int) $this->option('count')));
        $force = (bool) $this->option('force');

        $this->setAdminPassword((string) $this->option('admin-password'));

        $packageIds = Package::query()->where('is_active', true)->orderBy('id')->pluck('id')->all();
        if ($packageIds === []) {
            $this->error('No active packages — create a package first.');

            return self::FAILURE;
        }

        $areaId = \App\Models\Area::query()->value('id');
        $zoneId = \App\Models\Zone::query()->value('id');
        $subzoneId = \App\Models\Subzone::query()->value('id');
        $mikrotikId = \App\Models\MikrotikServer::query()->value('id');
        $collectorId = User::query()->whereKey(3)->value('id') ?? User::query()->value('id');
        $tenantId = 1;

        $created = 0;
        $skipped = 0;

        for ($i = 1; $i <= $count; $i++) {
            $code = 'TST'.str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $phone = '01710'.str_pad((string) (100000 + $i), 6, '0', STR_PAD_LEFT);

            $existing = Customer::query()->where('customer_code', $code)->first();
            if ($existing !== null && ! $force) {
                $this->line("Skip {$code} — already exists");
                $skipped++;

                continue;
            }

            $profile = $this->profiles[($i - 1) % count($this->profiles)];
            $packageId = $packageIds[($i - 1) % count($packageIds)];
            $pkg = Package::query()->find($packageId);
            $monthly = (float) ($pkg?->price_monthly ?? 500);

            $status = match ($i % 5) {
                0 => CustomerStatus::SUSPENDED,
                1 => CustomerStatus::ACTIVE,
                2 => CustomerStatus::ACTIVE,
                3 => CustomerStatus::EXPIRED,
                default => CustomerStatus::ACTIVE,
            };

            $subscriberType = match ($i % 4) {
                0 => SubscriberType::VIP,
                1 => SubscriberType::STANDARD,
                2 => SubscriberType::FREE,
                default => SubscriberType::STANDARD,
            };

            $joined = now()->subMonths(random_int(1, 18))->startOfDay();
            $expires = $status === CustomerStatus::EXPIRED
                ? now()->subDays(3)->toDateString()
                : now()->addDays(random_int(5, 45))->toDateString();

            $meta = [
                'static_ip' => '10.10.'.(10 + $i).'.'.(100 + $i),
                'mac_binding' => sprintf('1A:2B:3C:4D:5E:%02X', $i),
                'vlan' => (string) (100 + $i),
                'epon_port' => '0/1/'.($i % 8 + 1),
                'onu_mac' => sprintf('00:11:22:33:44:%02X', $i),
                'onu_rent' => 50.0,
                'onu_installment' => 200.0,
                'onu_deposit' => 500.0,
                'router_rent' => 30.0,
                'gps_lat' => '23.81'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'gps_lng' => '90.41'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'collector_id' => $collectorId,
                'technician_id' => $collectorId,
                'installation_date' => $joined->toDateString(),
                'installation_charge' => 500.0,
                'cable_length_m' => 80 + ($i * 5),
                'installation_status' => $i % 3 === 0 ? 'pending' : 'completed',
                'portal_otp_login' => $i % 2 === 0,
                'portal_2fa' => false,
                'notify_sms' => true,
                'notify_whatsapp' => $i % 2 === 0,
                'notify_email' => false,
                'notify_push' => false,
                'auto_invoice' => true,
                'auto_pppoe' => true,
                'auto_onu' => false,
                'auto_activate' => true,
                'auto_suspend' => $subscriberType !== SubscriberType::VIP,
                'auto_renew' => false,
                'tag_vip' => $subscriberType === SubscriberType::VIP,
                'tag_gaming' => str_contains(strtolower($profile['segment']), 'gaming'),
                'tag_corporate' => str_contains(strtolower($profile['segment']), 'corporate'),
                'tag_late_payer' => $i % 7 === 0,
                'discount_note' => $i % 3 === 0 ? 'Staff discount 10%' : '',
                'mikrotik_comment' => "Test client {$code}",
                'legacy_id' => 'ANET-'.(1000 + $i),
                'legacy_client_id' => 'OLD-'.$i,
            ];

            $attrs = [
                'tenant_id' => $tenantId,
                'customer_code' => $code,
                'name' => $profile['name'],
                'phone' => $phone,
                'email' => strtolower(str_replace([' ', '.'], ['', ''], $profile['name'])).'@test.anetbd.local',
                'nid_number' => '1990'.str_pad((string) $i, 7, '0', STR_PAD_LEFT),
                'area_id' => $areaId,
                'zone_id' => $zoneId,
                'subzone_id' => $subzoneId,
                'package_id' => $packageId,
                'status' => $status,
                'subscriber_type' => $subscriberType,
                'billing_mode' => 'postpaid',
                'billing_day' => min(28, $joined->day),
                'grace_period_days' => 10,
                'joined_at' => $joined->toDateString(),
                'service_expires_at' => $expires,
                'account_balance' => $i % 4 === 0 ? 150.0 : 0,
                'credit_limit' => 2000.0,
                'network_access_state' => $status === CustomerStatus::SUSPENDED ? 'suspended' : 'active',
                'mikrotik_secret_name' => $phone,
                'mikrotik_server_id' => $mikrotikId,
                'mikrotik_ppp_password' => 'ppp'.$i.'test',
                'portal_password' => Hash::make('portal'.$i),
                'segment' => $profile['segment'],
                'address' => "House {$i}, Road ".($i + 2).', Kazla, Dhaka',
                'notes' => "Test subscriber seeded for ISP Digital parity — {$code}",
                'kyc_status' => $i % 2 === 0 ? 'verified' : 'pending',
                'kyc_verified_at' => $i % 2 === 0 ? now()->subDays(10) : null,
                'kyc_notes' => 'Seeded test KYC',
                'late_fee_fixed' => 50,
                'late_fee_percent' => 2,
                'late_fee_period' => 'daily',
                'reconnection_fee_amount' => 100,
                'security_deposit_required' => 500,
                'security_deposit_collected' => $i % 2 === 0 ? 500 : 0,
                'meta' => $meta,
                'import_source' => 'manual',
            ];

            $customer = Customer::withoutEvents(function () use ($existing, $attrs): Customer {
                if ($existing !== null) {
                    return $existing->updateTrusted($attrs);
                }

                return Customer::createTrusted($attrs);
            });

            if ($existing !== null) {
                CustomerContact::query()->where('customer_id', $customer->id)->delete();
            }

            CustomerContact::query()->create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'label' => 'home',
                'phone' => $phone,
                'is_primary' => true,
                'is_whatsapp' => true,
            ]);

            CustomerContact::query()->create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'label' => 'office',
                'phone' => '01810'.str_pad((string) (200000 + $i), 6, '0', STR_PAD_LEFT),
                'is_primary' => false,
                'is_whatsapp' => false,
            ]);

            if ($existing === null && $i % 2 === 1) {
                $inv = Invoice::createTrusted([
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
                    'amount_paid' => $i % 3 === 0 ? $monthly : 0,
                    'status' => $i % 3 === 0 ? 'paid' : 'open',
                ]);

                if ($i % 3 === 0) {
                    Payment::createTrusted([
                        'tenant_id' => $tenantId,
                        'customer_id' => $customer->id,
                        'invoice_id' => $inv->id,
                        'amount' => $monthly,
                        'method' => 'cash',
                        'status' => 'completed',
                        'paid_at' => now()->subDays(2),
                        'recorded_by' => $collectorId,
                        'receipt_number' => 'RCP-TST-'.$i,
                    ]);
                }
            }

            $this->info("Created {$code} — {$profile['name']} ({$phone})");
            $created++;
        }

        $this->newLine();
        $this->table(
            ['Result', 'Count'],
            [
                ['Created', $created],
                ['Skipped', $skipped],
                ['Total subscribers', Customer::query()->count()],
            ],
        );
        $this->line('Admin login: admin@isp.local / '.(string) $this->option('admin-password'));
        $this->line('View: /admin/subscribers');

        return self::SUCCESS;
    }

    private function setAdminPassword(string $password): void
    {
        $admin = User::query()->where('email', 'admin@isp.local')->first();
        if ($admin === null) {
            $this->warn('admin@isp.local not found — password not changed.');

            return;
        }

        $admin->forceFill(['password' => Hash::make($password)])->save();
        $this->info('Admin password set for admin@isp.local');
    }
}
