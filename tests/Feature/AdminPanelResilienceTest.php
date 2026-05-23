<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPanelResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_render_filament_layout(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        $response = $this->actingAs($user)->get('/admin/online-clients');

        $response->assertOk();
        $response->assertSee('fi-body', false);
        $response->assertSee('Live PPP / online clients', false);
    }

    public function test_pending_gateway_payments_page_renders(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        $response = $this->actingAs($user)->get('/admin/pending-gateway-payments');

        $response->assertOk();
        $response->assertSee('fi-page', false);
        $response->assertSee('Pending', false);
    }

    public function test_optical_noc_page_renders(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');

        $response = $this->actingAs($user)->get('/admin/optical-noc');

        $response->assertOk();
        $response->assertSee('Optical Database', false);
    }

    public function test_command_palette_markup_is_well_formed(): void
    {
        $html = view('filament.hooks.command-palette', [
            'commandItems' => [],
        ])->render();

        $this->assertStringContainsString('<div', $html);
        $this->assertStringContainsString('</div>', $html);
        $this->assertStringNotContainsString('</motion.div>', $html);
        $this->assertStringNotContainsString('<motion.div', $html);
    }
}
