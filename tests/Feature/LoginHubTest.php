<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_hub_lists_all_portals(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Choose how you want to sign in', false)
            ->assertSee('Customer portal', false)
            ->assertSee('Admin / staff', false)
            ->assertSee('Reseller / partner', false)
            ->assertSee(route('portal.login', [], false), false)
            ->assertSee('/admin/login', false)
            ->assertSee('/reseller/login', false);
    }

    public function test_legacy_portal_login_redirects_to_customer_login(): void
    {
        $this->get('/portal/login')
            ->assertRedirect('/login/customer');
    }

    public function test_customer_login_page_loads(): void
    {
        $this->get('/login/customer')
            ->assertOk()
            ->assertSee('Customer code, phone, or email', false)
            ->assertSee(route('login.hub', [], false), false);
    }
}
