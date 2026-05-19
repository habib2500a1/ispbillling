<?php

namespace Tests\Feature;

use App\Models\BandwidthSample;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PppSessionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalUsageTest extends TestCase
{
    use RefreshDatabase;

    private function customerWithPortal(): Customer
    {
        $package = Package::query()->create([
            'name' => 'Portal Pkg',
            'type' => 'residential',
            'download_mbps' => 20,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Portal User',
            'phone' => '01710008899',
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'tenant_id' => 1,
            'portal_password' => Hash::make('secret123'),
        ]);

        PppSessionLog::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'session_key' => 'test-live',
            'username' => $customer->customer_code,
            'bytes_in' => 50_000_000,
            'bytes_out' => 10_000_000,
            'peak_rate_in_bps' => 25_000_000,
            'peak_rate_out_bps' => 5_000_000,
            'started_at' => now(),
            'status' => 'active',
            'meta' => ['rate_download_bps' => 25_000_000, 'rate_upload_bps' => 5_000_000],
        ]);

        BandwidthSample::query()->create([
            'tenant_id' => 1,
            'customer_id' => $customer->id,
            'session_key' => 'test-live',
            'username' => $customer->customer_code,
            'bytes_in' => 1000,
            'bytes_out' => 200,
            'rate_in_bps' => 10_000_000,
            'rate_out_bps' => 2_000_000,
            'sampled_at' => now(),
        ]);

        return $customer;
    }

    public function test_customer_can_view_live_usage_page(): void
    {
        $customer = $this->customerWithPortal();

        $this->actingAs($customer, 'customer')
            ->get('/portal/usage')
            ->assertOk()
            ->assertSee('Live internet usage', false);
    }

    public function test_live_usage_json_endpoint(): void
    {
        $customer = $this->customerWithPortal();

        $this->actingAs($customer, 'customer')
            ->getJson('/portal/usage/live')
            ->assertOk()
            ->assertJsonPath('online', true)
            ->assertJsonStructure(['download_bps', 'upload_bps', 'chart']);
    }

    public function test_customer_can_change_portal_password(): void
    {
        $customer = $this->customerWithPortal();

        $this->actingAs($customer, 'customer')
            ->post('/portal/account/password', [
                'current_password' => 'secret123',
                'password' => 'newpass99',
                'password_confirmation' => 'newpass99',
            ])
            ->assertRedirect(route('portal.profile.index'));

        $customer->refresh();
        $this->assertTrue(Hash::check('newpass99', (string) $customer->portal_password));
    }
}
