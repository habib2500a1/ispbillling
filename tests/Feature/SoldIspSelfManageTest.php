<?php

namespace Tests\Feature;

use App\Models\AddressField;
use App\Models\CustomersInfo;
use App\Models\SmsTemplate;
use App\Services\Saas\OperatorProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SoldIspSelfManageTest extends TestCase
{
    use RefreshDatabase;

    private function sellOperator(): array
    {
        Role::findOrCreate('Super Admin', 'web');
        Permission::findOrCreate('saas-sell', 'web');
        Permission::findOrCreate('payment-collection', 'web');
        Permission::findOrCreate('payment-collection-invoice', 'web');
        Permission::findOrCreate('address-setup', 'web');
        Permission::findOrCreate('sms-setup', 'web');
        Permission::findOrCreate('site-settings', 'web');

        $owner = \App\Models\User::factory()->create([
            'name' => 'Platform Owner',
            'email' => 'owner-self@isp.com',
        ]);
        $owner->assignRole('Super Admin');
        $this->actingAs($owner);

        AddressField::create([
            'label' => 'zone',
            'input_type' => 'dropdown',
            'dropdown_list' => json_encode(['muktinagor']),
        ]);

        $operator = app(OperatorProvisioningService::class)->sell([
            'company' => 'Radiant ISP',
            'contact_name' => 'Radiant Admin',
            'email' => 'radiant-self@isp.com',
            'password' => 'password12',
            'plan' => 'starter',
        ]);
        $buyer = \App\Models\User::where('email', 'radiant-self@isp.com')->firstOrFail();

        return [$owner, $buyer, $operator];
    }

    public function test_sold_isp_admin_manages_own_zones_invoices_sms_but_cannot_sell(): void
    {
        [$owner, $buyer] = $this->sellOperator();

        $this->actingAs($buyer);
        $this->assertFalse($buyer->can('saas-sell'));
        $this->assertTrue($buyer->can('address-setup'));
        $this->assertTrue($buyer->can('sms-setup'));
        $this->assertTrue($buyer->can('payment-collection'));

        $this->get(route('address-setup'))->assertOk()->assertDontSee('muktinagor');
        $this->get(route('sms-setup'))->assertOk();
        $this->get(route('payment-invoice'))->assertOk();
        $this->get(route('admin.saas-operators'))->assertForbidden();

        AddressField::create([
            'label' => 'Zone',
            'input_type' => 'dropdown',
            'dropdown_list' => json_encode(['k.m.das lane', 'shamibagh']),
            'saas_operator_id' => $buyer->saas_operator_id,
        ]);

        $this->actingAs($buyer);
        $buyerZones = AddressField::query()->pluck('label')->all();
        $this->assertContains('Zone', $buyerZones);
        $this->assertNotContains('zone', $buyerZones);

        $this->actingAs($owner);
        $ownerZones = AddressField::query()->pluck('label')->all();
        $this->assertContains('zone', $ownerZones);
        $this->assertNotContains('Zone', $ownerZones);
    }

    public function test_sms_templates_are_copied_per_sold_isp(): void
    {
        [$owner, $buyer] = $this->sellOperator();

        $this->actingAs($owner);
        $platform = SmsTemplate::query()->count();
        $this->assertGreaterThan(0, $platform);

        $this->actingAs($buyer);
        $tenant = SmsTemplate::query()->count();
        $this->assertGreaterThan(0, $tenant);

        $buyerTpl = SmsTemplate::query()->first();
        $buyerTpl->update(['template' => 'Radiant only text']);

        $this->actingAs($owner);
        $this->assertFalse(SmsTemplate::query()->where('template', 'Radiant only text')->exists());
    }
}
