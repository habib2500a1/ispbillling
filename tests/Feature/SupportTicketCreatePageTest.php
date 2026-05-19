<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportTicketCreatePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_isp_admin_can_open_support_ticket_create_page(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');

        $this->actingAs($user)
            ->get('/admin/support-tickets/create')
            ->assertOk();
    }
}
