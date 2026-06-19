<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageCustomerSearchSettings;
use App\Models\User;
use App\Support\CustomerSearchSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManageCustomerSearchSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_isp_admin_can_open_customer_search_settings(): void
    {
        Role::findOrCreate('isp-admin');
        $user = User::factory()->create();
        $user->assignRole('isp-admin');

        $this->actingAs($user)
            ->get(ManageCustomerSearchSettings::getUrl())
            ->assertOk()
            ->assertSee('Customer search', false);
    }

    public function test_master_key_is_derived_from_app_key(): void
    {
        config(['app.key' => 'base64:test-key-for-search']);

        $expected = hash('sha256', 'base64:test-key-for-search|'.CustomerSearchSettings::MASTER_KEY_SALT);

        $this->assertSame($expected, CustomerSearchSettings::masterKey());
    }
}
