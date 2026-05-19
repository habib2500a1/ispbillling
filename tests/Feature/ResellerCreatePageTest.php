<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ResellerCreatePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_isp_admin_can_open_reseller_create_page(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create(['tenant_id' => 1]);
        $user->assignRole('isp-admin');

        $response = $this->actingAs($user)->get('/admin/resellers/create');

        if ($response->status() === 308) {
            $response = $this->followRedirects($response);
        }

        $response->assertOk()->assertSee('Partner profile', false);
    }
}
