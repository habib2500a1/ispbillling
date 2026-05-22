<?php

namespace Tests\Unit;

use App\Support\BkashSettings;
use App\Support\PortalPaymentGateways;
use App\Support\PublicPaymentMethod;
use Tests\TestCase;

final class PublicPaymentMethodTest extends TestCase
{
    public function test_disabled_gateways_are_not_listed(): void
    {
        config([
            'bkash.enabled' => true,
            'bkash.gateway_type' => BkashSettings::GATEWAY_PERSONAL,
            'bkash.personal_number' => '01710000001',
            'bkash.channels' => [BkashSettings::CHANNEL_PUBLIC_PAY],
            'nagad.enabled' => false,
            'sslcommerz.enabled' => false,
            'rocket.enabled' => false,
            'piprapay.enabled' => true,
            'piprapay.api_key' => 'test-key',
        ]);

        $methods = PortalPaymentGateways::methodsForPublicBillPay();

        $this->assertCount(2, $methods);
        $this->assertSame(['bkash', 'piprapay'], array_column($methods, 'gateway'));
    }

    public function test_nagad_personal_appears_when_configured(): void
    {
        config([
            'bkash.enabled' => false,
            'bkash.channels' => [],
            'piprapay.enabled' => false,
            'nagad.enabled' => true,
            'nagad.gateway_type' => 'personal',
            'nagad.personal_number' => '01700000099',
        ]);

        $methods = PublicPaymentMethod::collect(BkashSettings::CHANNEL_PUBLIC_PAY);

        $this->assertCount(1, $methods);
        $this->assertSame('nagad', $methods[0]['gateway']);
        $this->assertSame('Personal', $methods[0]['badge']);
    }

    public function test_bkash_hidden_when_public_pay_channel_off(): void
    {
        config([
            'bkash.enabled' => true,
            'bkash.gateway_type' => BkashSettings::GATEWAY_PERSONAL,
            'bkash.personal_number' => '01710000001',
            'bkash.channels' => [BkashSettings::CHANNEL_PORTAL],
        ]);

        $methods = PortalPaymentGateways::methodsForPublicBillPay();

        $this->assertFalse(collect($methods)->contains('gateway', 'bkash'));
    }
}
