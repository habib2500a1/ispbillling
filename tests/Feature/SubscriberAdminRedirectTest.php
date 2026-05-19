<?php

namespace Tests\Feature;

use Tests\TestCase;

class SubscriberAdminRedirectTest extends TestCase
{
    public function test_legacy_admin_customers_paths_redirect_to_subscribers(): void
    {
        $this->get('/admin/customers')->assertRedirect('/admin/subscribers');
        $this->get('/admin/customers/create')->assertRedirect('/admin/subscribers/create');
        $this->get('/admin/customers/99/edit')->assertRedirect('/admin/subscribers/99/edit');
    }
}
