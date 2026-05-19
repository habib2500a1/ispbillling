<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageCompanySetup;
use App\Models\AppSetting;
use App\Models\User;
use App\Support\CompanyBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanySetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_setup_page_requires_privileged_role(): void
    {
        $user = User::factory()->create(['tenant_id' => 1]);
        Role::findOrCreate('cashier');
        $user->assignRole('cashier');

        $this->actingAs($user)
            ->get(ManageCompanySetup::getUrl())
            ->assertForbidden();
    }

    public function test_isp_admin_can_save_company_profile(): void
    {
        $user = User::factory()->create(['tenant_id' => 1]);
        Role::findOrCreate('isp-admin');
        $user->assignRole('isp-admin');

        Livewire::actingAs($user)
            ->test(ManageCompanySetup::class)
            ->fillForm([
                'company_name' => 'Test ISP Ltd',
                'company_tagline' => 'Fast fiber internet',
                'company_phone' => '01711111111',
                'company_email' => 'billing@testisp.local',
                'company_address' => 'Dhaka, Bangladesh',
                'company_website' => 'https://testisp.local',
                'company_tax_id' => 'BIN-123456',
                'invoice_show_logo' => true,
                'invoice_number_prefix' => 'TST',
                'invoice_number_year_infix' => true,
                'invoice_footer' => 'Thank you for choosing Test ISP.',
                'invoice_terms' => 'Pay within due date.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        AppSetting::syncToRuntimeConfig();

        $this->assertSame('Test ISP Ltd', CompanyBranding::name());
        $this->assertSame('01711111111', CompanyBranding::phone());
        $this->assertSame('billing@testisp.local', CompanyBranding::email());
        $this->assertSame('TST', config('billing.invoice_number_prefix'));
    }
}
