<?php

namespace Tests\Feature;

use App\Filament\Pages\BillingOverview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BillingOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_isp_admin_can_render_billing_center_hub(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');

        Livewire::actingAs($user)
            ->test(BillingOverview::class)
            ->assertSuccessful()
            ->assertSee('Billing center', false)
            ->assertSee('Invoices', false)
            ->assertSee('outstanding', false);
    }
}
