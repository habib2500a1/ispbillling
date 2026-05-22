<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Support\BkashSettings;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PaymentLink;
use App\Services\BillPayment\PaymentLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBillPaymentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Customer, 1: Invoice}
     */
    private function seedCustomerInvoice(string $code = 'TEST1001', float $total = 500): array
    {
        $package = Package::query()->create([
            'name' => '10 Mbps',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => $total,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Public Pay Test',
            'phone' => '01711112222',
            'customer_code' => $code,
            'status' => 'active',
            'billing_day' => 1,
            'package_id' => $package->id,
            'account_balance' => 0,
        ]);

        $invoice = Invoice::query()->create([
            'customer_id' => $customer->id,
            'tenant_id' => $customer->tenant_id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => $total,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => $total,
            'amount_paid' => 0,
            'status' => 'open',
        ]);

        return [$customer, $invoice];
    }

    public function test_bill_payment_index_loads(): void
    {
        $this->get(route('bill-payment.index'))
            ->assertOk()
            ->assertSee('Pay your bill');
    }

    public function test_legacy_bill_payment_redirect(): void
    {
        $this->get('/BillPayment/Index')
            ->assertRedirect('/pay');
    }

    public function test_lookup_with_invalid_code_fails(): void
    {
        $this->post(route('bill-payment.lookup'), ['client_code' => 'INVALID999'])
            ->assertSessionHasErrors('client_code');
    }

    public function test_lookup_and_invoice_flow_without_otp(): void
    {
        config([
            'bill_payment.otp.enabled' => false,
            'bkash.enabled' => true,
            'bill_payment.allow_partial' => true,
        ]);
        AppSetting::syncPublicPaymentGatewayFlags();

        $this->seedCustomerInvoice('TEST1001', 500);

        $this->post(route('bill-payment.lookup'), ['client_code' => 'TEST1001'])
            ->assertRedirect(route('bill-payment.invoice'));

        $this->get(route('bill-payment.invoice'))
            ->assertOk()
            ->assertSee('TEST1001')
            ->assertSee('500.00')
            ->assertSee('Pay full due')
            ->assertSee('Wallet top-up')
            ->assertSee('Payment link');
    }

    public function test_invoice_tabs_wallet_and_link(): void
    {
        config(['bill_payment.otp.enabled' => false]);

        $this->seedCustomerInvoice('TAB2001', 300);

        $this->post(route('bill-payment.lookup'), ['client_code' => 'TAB2001'])
            ->assertRedirect(route('bill-payment.invoice'));

        $this->get(route('bill-payment.invoice', ['tab' => 'wallet']))
            ->assertOk()
            ->assertSee('Advance / wallet top-up');

        $this->get(route('bill-payment.invoice', ['tab' => 'link']))
            ->assertOk()
            ->assertSee('Share payment link');
    }

    public function test_partial_pay_validates_amount(): void
    {
        config(['bill_payment.otp.enabled' => false, 'bill_payment.allow_partial' => true]);

        [, $invoice] = $this->seedCustomerInvoice('PART3001', 500);

        $this->post(route('bill-payment.lookup'), ['client_code' => 'PART3001']);
        $this->get(route('bill-payment.invoice'));

        $this->post(route('bill-payment.pay', $invoice), ['gateway' => 'bkash', 'amount' => 5])
            ->assertSessionHasErrors('amount');

        $this->post(route('bill-payment.pay', $invoice), ['gateway' => 'bkash', 'amount' => 600])
            ->assertSessionHasErrors('amount');
    }

    public function test_wallet_topup_validates_minimum(): void
    {
        config([
            'bill_payment.otp.enabled' => false,
            'bill_payment.wallet_topup_enabled' => true,
            'bill_payment.wallet_topup_min' => 100,
        ]);

        $this->seedCustomerInvoice('WAL4001', 0);

        $this->post(route('bill-payment.lookup'), ['client_code' => 'WAL4001']);

        $this->post(route('bill-payment.wallet'), ['amount' => 50])
            ->assertSessionHasErrors('amount');
    }

    public function test_payment_link_opens_verified_session(): void
    {
        config(['bill_payment.otp.enabled' => true]);

        [$customer, $invoice] = $this->seedCustomerInvoice('LINK5001', 400);

        $link = app(PaymentLinkService::class)->create(
            $customer,
            PaymentLink::PURPOSE_INVOICE,
            $invoice,
            200.0,
        );

        $this->get(route('bill-payment.link', ['token' => $link->token]))
            ->assertRedirect(route('bill-payment.invoice'));

        $this->get(route('bill-payment.invoice'))
            ->assertOk()
            ->assertSee('LINK5001');
    }

    public function test_public_gateway_flags_follow_panel_bkash_enabled(): void
    {
        AppSetting::putValue('bkash.enabled', '1');
        AppSetting::putValue('bkash.gateway_type', BkashSettings::GATEWAY_PERSONAL);
        AppSetting::putValue('bkash.personal_number', '01710000001');
        AppSetting::syncToRuntimeConfig();
        config([
            'bkash.channels' => [BkashSettings::CHANNEL_PUBLIC_PAY],
            'bkash.gateway_type' => BkashSettings::GATEWAY_PERSONAL,
            'bkash.personal_number' => '01710000001',
        ]);

        $this->assertTrue(config('bkash.enabled'));
        AppSetting::syncPublicPaymentGatewayFlags();
        $this->assertTrue(config('bill_payment.gateways.bkash'));

        config(['bill_payment.otp.enabled' => false]);
        $this->seedCustomerInvoice('GW8001', 500);

        $this->post(route('bill-payment.lookup'), ['client_code' => 'GW8001']);
        $this->get(route('bill-payment.invoice'))
            ->assertOk()
            ->assertSee('bKash');

        AppSetting::query()->where('key', 'bkash.enabled')->delete();
        AppSetting::restoreConfigKeyFromEnv('bkash.enabled');
        AppSetting::syncToRuntimeConfig();
    }

    public function test_bill_pay_otp_can_be_disabled_via_app_setting(): void
    {
        AppSetting::putValue('bill_payment.otp.enabled', '0');
        AppSetting::syncToRuntimeConfig();

        $this->assertFalse(config('bill_payment.otp.enabled'));

        $this->seedCustomerInvoice('OTP7001', 200);

        $this->post(route('bill-payment.lookup'), ['client_code' => 'OTP7001'])
            ->assertRedirect(route('bill-payment.invoice'));

        AppSetting::query()->where('key', 'bill_payment.otp.enabled')->delete();
        AppSetting::restoreConfigKeyFromEnv('bill_payment.otp.enabled');
        AppSetting::syncToRuntimeConfig();
    }

    public function test_create_payment_link_from_invoice_page(): void
    {
        config(['bill_payment.otp.enabled' => false]);

        [$customer] = $this->seedCustomerInvoice('LINK6001', 350);

        $this->post(route('bill-payment.lookup'), ['client_code' => 'LINK6001']);

        $this->post(route('bill-payment.payment-link.create'), [
            'purpose' => 'invoice',
            'amount' => 150,
            'send_sms' => false,
        ])
            ->assertRedirect(route('bill-payment.invoice', ['tab' => 'link']))
            ->assertSessionHas('payment_link_url');

        $this->assertDatabaseHas('payment_links', [
            'customer_id' => $customer->id,
            'purpose' => PaymentLink::PURPOSE_INVOICE,
            'amount' => 150,
        ]);
    }
}
