<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerNote;
use App\Models\Package;
use App\Support\CustomerStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SubscriberManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_login_accepts_alternate_contact_phone(): void
    {
        $package = Package::query()->create([
            'name' => 'P',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 100,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Alt Phone',
            'phone' => '01710000001',
            'status' => CustomerStatus::ACTIVE,
            'billing_day' => 1,
            'package_id' => $package->id,
            'portal_password' => Hash::make('secret'),
        ]);

        CustomerContact::query()->create([
            'customer_id' => $customer->id,
            'tenant_id' => $customer->tenant_id,
            'label' => 'office',
            'phone' => '01710000099',
            'is_primary' => false,
        ]);

        $found = Customer::findForPortalLogin('01710000099');
        $this->assertNotNull($found);
        $this->assertSame($customer->id, $found->id);
    }

    public function test_status_change_creates_history_note(): void
    {
        $package = Package::query()->create([
            'name' => 'P2',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 100,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Note Test',
            'phone' => '01710000002',
            'status' => CustomerStatus::ACTIVE,
            'billing_day' => 1,
            'package_id' => $package->id,
        ]);

        $customer->update(['status' => CustomerStatus::SUSPENDED]);

        $this->assertDatabaseHas('customer_notes', [
            'customer_id' => $customer->id,
            'category' => 'status_change',
        ]);

        $note = CustomerNote::query()->where('customer_id', $customer->id)->first();
        $this->assertStringContainsString('Suspended', $note->body);
    }

    public function test_primary_contact_syncs_main_phone(): void
    {
        $package = Package::query()->create([
            'name' => 'P3',
            'type' => 'residential',
            'download_mbps' => 10,
            'price_monthly' => 100,
            'setup_fee' => 0,
            'vat_percent' => 0,
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Sync',
            'phone' => '01710000003',
            'status' => CustomerStatus::ACTIVE,
            'billing_day' => 1,
            'package_id' => $package->id,
        ]);

        CustomerContact::query()->create([
            'customer_id' => $customer->id,
            'tenant_id' => $customer->tenant_id,
            'label' => 'mobile',
            'phone' => '01719998877',
            'is_primary' => true,
        ]);

        $customer->syncPrimaryPhoneFromContacts();

        $this->assertSame('01719998877', $customer->fresh()->phone);
    }

    public function test_customer_status_options_include_expired(): void
    {
        $this->assertArrayHasKey(CustomerStatus::EXPIRED, CustomerStatus::options());
    }
}
