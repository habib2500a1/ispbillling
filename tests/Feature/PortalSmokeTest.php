<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function guestPortalPagesProvider(): array
    {
        return [
            'login hub' => ['/login', 'Choose how you want to sign in'],
            'customer login' => ['/login/customer', 'Customer code, phone, or email'],
            'signup' => ['/portal/signup', 'Request a new connection'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function authenticatedPortalPagesProvider(): array
    {
        return [
            'dashboard' => ['/portal', 'Live dashboard'],
            'bills' => ['/portal/bills', 'My bills'],
            'invoices' => ['/portal/invoices', 'Invoice'],
            'packages' => ['/portal/packages', 'Internet packages'],
            'profile' => ['/portal/profile', 'Profile'],
            'payments' => ['/portal/payments', 'Payment'],
            'usage' => ['/portal/usage', 'START'],
            'onu' => ['/portal/onu', 'ONU'],
            'equipment' => ['/portal/equipment', 'Equipment'],
            'tickets' => ['/portal/tickets', 'ticket'],
            'notifications' => ['/portal/notifications', 'Notification'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('guestPortalPagesProvider')]
    public function test_guest_portal_pages_load(string $url, string $see): void
    {
        $this->get($url)->assertOk()->assertSee($see, false);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('authenticatedPortalPagesProvider')]
    public function test_authenticated_portal_pages_load(string $url, string $see): void
    {
        $customer = $this->makePortalCustomer();

        $response = $this->actingAs($customer, 'customer')->get($url);

        if ($response->status() === 308 || $response->status() === 301) {
            $response = $this->followRedirects($response);
        }

        $response->assertOk()->assertSee($see, false);
    }

    public function test_portal_dashboard_live_json_for_authenticated_customer(): void
    {
        $customer = $this->makePortalCustomer();

        $this->actingAs($customer, 'customer')
            ->getJson('/portal/dashboard/live')
            ->assertOk();
    }
}
