<?php

namespace Tests\Feature;

use App\Filament\Resources\SupportAssignmentRuleResource\Pages\ManageSupportAssignmentRules;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportAssignmentRulePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_isp_admin_can_render_manage_support_assignment_rules(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');

        Livewire::actingAs($user)
            ->test(ManageSupportAssignmentRules::class)
            ->assertSuccessful();
    }

    public function test_isp_manager_can_render_manage_support_assignment_rules(): void
    {
        Role::findOrCreate('isp-manager');
        $user = User::factory()->create();
        $user->assignRole('isp-manager');

        Livewire::actingAs($user)
            ->test(ManageSupportAssignmentRules::class)
            ->assertSuccessful();
    }

    public function test_isp_admin_can_http_get_support_assignment_rules_index(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');

        $this->actingAs($user)
            ->get('/admin/support-assignment-rules')
            ->assertOk();
    }

    public function test_isp_engineer_cannot_access_support_assignment_rules_index(): void
    {
        Role::findOrCreate('isp-engineer');
        $user = User::factory()->create();
        $user->assignRole('isp-engineer');

        $this->actingAs($user)
            ->get('/admin/support-assignment-rules')
            ->assertForbidden();
    }
}
