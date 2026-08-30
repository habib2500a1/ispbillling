<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalBkashRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_bkash_initiate_lives_on_the_app_host_not_portal_subdomain(): void
    {
        config(['app.url' => 'https://anetbd.com']);

        $this->assertSame(
            'https://anetbd.com/pay/start/bkash?amount=500',
            route('pay.start.bkash', ['amount' => 500], true)
        );
        $this->assertSame(
            'https://anetbd.com/payment/bkash/initiate?amount=500',
            route('payment.bkash.initiate', ['amount' => 500], true)
        );
        $this->assertStringNotContainsString('portal.anetbd.com', route('payment.bkash.initiate', ['amount' => 500], true));
        $this->assertStringNotContainsString('portal.anetbd.com', route('payment.bkash.callback', [], true));
    }

    public function test_main_host_bkash_initiate_is_not_a_missing_page(): void
    {
        $this->get('/payment/bkash/initiate?amount=500')
            ->assertRedirect();
    }
}
