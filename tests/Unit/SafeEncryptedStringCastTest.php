<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafeEncryptedStringCastTest extends TestCase
{
    use RefreshDatabase;

    public function test_corrupt_ciphertext_does_not_throw_when_loading_customer(): void
    {
        $package = Package::query()->create([
            'name' => 'P1',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 100,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'T',
            'phone' => '01800000000',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'network_access_state' => 'active',
        ]);

        $customer->getConnection()->table('customers')->where('id', $customer->id)->update([
            'mikrotik_ppp_password' => 'not-valid-ciphertext',
        ]);

        $fresh = Customer::query()->withoutGlobalScopes()->find($customer->id);
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->mikrotik_ppp_password);
    }
}
