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
