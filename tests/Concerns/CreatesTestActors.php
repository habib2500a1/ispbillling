<?php

namespace Tests\Concerns;

use App\Models\Customer;
use App\Models\Package;
use App\Models\User;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

trait CreatesTestActors
{
    protected function fakeTenant(int $tenantId = 1): void
    {
        TenantResolver::fake($tenantId);
    }

    protected function seedRole(string $name, string $guard = 'web'): Role
    {
        return Role::findOrCreate($name, $guard);
    }

    protected function makeAdminUser(array $overrides = []): User
    {
        $this->seedRole('isp-admin');

        return tap(User::factory()->create(array_merge(['tenant_id' => 1], $overrides)), function (User $user): void {
            $user->assignRole('isp-admin');
        });
    }

    protected function makePortalCustomer(array $overrides = []): Customer
    {
        $package = Package::query()->create(array_merge([
            'tenant_id' => 1,
            'name' => 'Portal Test Pkg',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ], $overrides['package'] ?? []));
        unset($overrides['package']);

        return Customer::query()->create(array_merge([
            'tenant_id' => 1,
            'name' => 'Portal User',
            'phone' => '017'.random_int(10000000, 99999999),
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'customer_code' => 'P'.random_int(100000, 999999),
            'portal_password' => Hash::make('secret12'),
        ], $overrides));
    }
}
