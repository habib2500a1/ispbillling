<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PortalLoginOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_login_without_otp_logs_in_directly(): void
    {
        Config::set('portal.otp.enabled', false);

        $customer = $this->makeCustomerWithPortal('secret12', 'sub@example.com');

        $this->post('/portal/login', [
            'login' => $customer->customer_code,
            'password' => 'secret12',
            '_token' => csrf_token(),
        ])->assertRedirect(route('portal.dashboard'));

        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_portal_login_with_otp_requires_email_when_not_log_only(): void
    {
        Config::set('portal.otp.enabled', true);
        Config::set('portal.otp.log_delivery_only', false);

        $customer = $this->makeCustomerWithPortal('secret12', null);

        $this->post('/portal/login', [
            'login' => $customer->customer_code,
            'password' => 'secret12',
            '_token' => csrf_token(),
        ])->assertSessionHasErrors('login');

        $this->assertGuest('customer');
    }

    public function test_portal_login_with_otp_and_log_only_completes_after_code(): void
    {
        Mail::fake();

        Config::set('portal.otp.enabled', true);
        Config::set('portal.otp.log_delivery_only', true);

        $customer = $this->makeCustomerWithPortal('secret12', null);

        $this->post('/portal/login', [
            'login' => $customer->customer_code,
            'password' => 'secret12',
            '_token' => csrf_token(),
        ])->assertRedirect(route('portal.login.otp'));

        $this->assertGuest('customer');

        \Illuminate\Support\Facades\Cache::put(
            'portal_login_otp:'.$customer->id,
            hash('sha256', '111111'),
            now()->addMinutes(10)
        );

        $this->post('/portal/login/otp', [
            'code' => '111111',
            '_token' => csrf_token(),
        ])->assertRedirect(route('portal.dashboard'));

        $this->assertAuthenticatedAs(Customer::query()->withoutGlobalScopes()->findOrFail($customer->id), 'customer');
    }

    public function test_abandon_query_clears_pending_otp_session(): void
    {
        Config::set('portal.otp.enabled', true);
        Config::set('portal.otp.log_delivery_only', true);

        $customer = $this->makeCustomerWithPortal('secret12', null);

        $this->post('/portal/login', [
            'login' => $customer->customer_code,
            'password' => 'secret12',
            '_token' => csrf_token(),
        ])->assertRedirect(route('portal.login.otp'));

        $this->get(route('portal.login', ['abandon' => '1']))
            ->assertOk();

        $this->get(route('portal.login.otp'))
            ->assertRedirect(route('portal.login'));
    }

    private function makeCustomerWithPortal(string $plainPassword, ?string $email): Customer
    {
        $package = Package::query()->create([
            'name' => 'Plan',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 500,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        return Customer::query()->create([
            'name' => 'Portal User',
            'phone' => '017'.random_int(10000000, 99999999),
            'email' => $email,
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'portal_password' => Hash::make($plainPassword),
        ]);
    }
}
