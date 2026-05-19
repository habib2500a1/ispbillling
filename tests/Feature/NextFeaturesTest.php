<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Package;
use App\Models\HotspotVoucher;
use App\Models\SalesLead;
use App\Services\Hotspot\HotspotVoucherRedeemer;
use App\Services\Sales\SalesLeadConversionService;
use App\Services\WhatsApp\WhatsAppBotService;
use App\Support\CustomerStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NextFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_signup_creates_sales_lead(): void
    {
        config(['portal.signup.enabled' => true]);

        $response = $this->post('/portal/signup', [
            'name' => 'Rahim Uddin',
            'phone' => '01711112222',
            'email' => 'rahim@example.com',
            'address' => 'Mirpur',
        ]);

        $response->assertRedirect(route('portal.signup.success'));
        $this->assertDatabaseHas('sales_leads', [
            'name' => 'Rahim Uddin',
            'source' => 'website',
            'status' => SalesLead::STATUS_NEW,
        ]);
    }

    public function test_hotspot_voucher_redeem(): void
    {
        config(['hotspot.enabled' => true]);

        $voucher = HotspotVoucher::query()->create([
            'code' => 'ABCD-EFGH',
            'duration_hours' => 6,
            'status' => HotspotVoucher::STATUS_AVAILABLE,
        ]);

        $result = app(HotspotVoucherRedeemer::class)->redeem('abcd-efgh');
        $this->assertTrue($result['ok']);

        $voucher->refresh();
        $this->assertSame(HotspotVoucher::STATUS_USED, $voucher->status);
    }

    public function test_hotspot_portal_page_loads(): void
    {
        config(['hotspot.enabled' => true]);

        $this->get('/hotspot')->assertOk();
    }

    public function test_locale_switch_sets_session(): void
    {
        $response = $this->get('/locale/bn');

        $response->assertRedirect();
        $this->assertSame('bn', session('locale'));
    }

    public function test_whatsapp_webhook_verify(): void
    {
        config(['whatsapp_bot.verify_token' => 'test-verify-token']);

        $this->get('/api/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=test-verify-token&hub_challenge=12345')
            ->assertOk()
            ->assertSee('12345');
    }

    public function test_whatsapp_bot_menu_reply(): void
    {
        config(['whatsapp_bot.enabled' => true]);

        $reply = app(WhatsAppBotService::class)->buildReply('8801711112222', 'MENU');

        $this->assertStringContainsString('BALANCE', $reply);
    }

    public function test_whatsapp_bot_packages_reply(): void
    {
        Package::query()->create([
            'tenant_id' => 1,
            'name' => 'Home 20',
            'download_mbps' => 20,
            'upload_mbps' => 10,
            'price_monthly' => 500,
            'is_active' => true,
        ]);

        $reply = app(WhatsAppBotService::class)->buildReply('8801711112222', 'PACKAGES');

        $this->assertStringContainsString('Home 20', $reply);
    }

    public function test_sales_lead_kanban_move(): void
    {
        $lead = SalesLead::query()->create([
            'tenant_id' => 1,
            'name' => 'Pipeline Test',
            'source' => 'phone',
            'status' => SalesLead::STATUS_NEW,
        ]);

        app(\App\Services\Sales\SalesLeadKanbanService::class)->move($lead->id, SalesLead::STATUS_CONTACTED);
        $lead->refresh();

        $this->assertSame(SalesLead::STATUS_CONTACTED, $lead->status);
    }

    public function test_sales_lead_converts_to_customer(): void
    {
        $lead = SalesLead::query()->create([
            'tenant_id' => 1,
            'name' => 'Karim Ahmed',
            'phone' => '01799998888',
            'source' => 'website',
            'status' => SalesLead::STATUS_NEW,
        ]);

        $customer = app(SalesLeadConversionService::class)->convert($lead);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertSame(CustomerStatus::ACTIVE, $customer->status);
        $lead->refresh();
        $this->assertSame(SalesLead::STATUS_WON, $lead->status);
        $this->assertSame($customer->id, $lead->converted_customer_id);
    }
}
