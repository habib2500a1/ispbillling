<?php

namespace Tests\Feature;

use App\Filament\Pages\BillingReports;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BillingReportsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_isp_admin_can_open_monthly_reports(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');

        Livewire::actingAs($user)
            ->test(BillingReports::class)
            ->assertSuccessful();
    }
}
