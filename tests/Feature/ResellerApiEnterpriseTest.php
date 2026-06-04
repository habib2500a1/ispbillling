<?php

namespace Tests\Feature;

use App\Models\Reseller;
use App\Support\ResellerPortalPermission;
use App\Support\ResellerType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResellerApiEnterpriseTest extends TestCase
{
    use RefreshDatabase;

    private function tokenForFranchise(): string
    {
        $reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'API Franchise',
            'code' => 'RSL-API-ENT',
            'franchise_type' => ResellerType::FRANCHISE,
            'commission_type' => 'percent',
            'commission_value' => 10,
            'is_active' => true,
            'portal_password' => Hash::make('api-secret'),
            'portal_permissions' => ResellerPortalPermission::defaultsFor(ResellerType::FRANCHISE),
        ]);

        return $this->postJson('/api/v1/reseller/login', [
            'login' => $reseller->code,
            'password' => 'api-secret',
            'device_name' => 'test',
        ])->json('token');
    }

    public function test_sub_resellers_api_lists_children(): void
    {
        $parent = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'Parent API',
            'code' => 'RSL-PARENT',
            'franchise_type' => ResellerType::FRANCHISE,
            'commission_type' => 'percent',
            'commission_value' => 10,
            'is_active' => true,
            'portal_password' => Hash::make('api-secret'),
            'portal_permissions' => ResellerPortalPermission::defaultsFor(ResellerType::FRANCHISE),
        ]);

        Reseller::query()->create([
            'tenant_id' => 1,
            'parent_id' => $parent->id,
            'name' => 'Child API',
            'franchise_type' => ResellerType::SUB_RESELLER,
            'commission_type' => 'percent',
            'commission_value' => 5,
            'is_active' => true,
        ]);

        $token = $this->postJson('/api/v1/reseller/login', [
            'login' => $parent->code,
            'password' => 'api-secret',
            'device_name' => 'test',
        ])->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/reseller/sub-resellers')
            ->assertOk()
            ->assertJsonPath('partners.0.name', 'Child API');
    }

    public function test_announcements_and_due_account_endpoints(): void
    {
        $token = $this->tokenForFranchise();

        $this->withToken($token)
            ->getJson('/api/v1/reseller/announcements')
            ->assertOk()
            ->assertJsonStructure(['announcements']);

        $this->withToken($token)
            ->getJson('/api/v1/reseller/due-account')
            ->assertOk()
            ->assertJsonStructure(['summary', 'customer_breakdown', 'aging']);
    }

    public function test_internal_tickets_api_create_and_list(): void
    {
        $token = $this->tokenForFranchise();

        $this->withToken($token)
            ->postJson('/api/v1/reseller/internal-tickets', [
                'subject' => 'API billing issue',
                'body' => 'Need help with wholesale ledger.',
            ])
            ->assertCreated()
            ->assertJsonPath('ticket.subject', 'API billing issue');

        $this->withToken($token)
            ->getJson('/api/v1/reseller/internal-tickets')
            ->assertOk()
            ->assertJsonPath('tickets.0.subject', 'API billing issue');
    }

    public function test_staff_and_wallet_overview_api(): void
    {
        $token = $this->tokenForFranchise();

        $this->withToken($token)
            ->postJson('/api/v1/reseller/staff', [
                'name' => 'Collector One',
                'login' => 'collector1',
                'password' => 'secret1234',
                'portal_permissions' => [
                    ResellerPortalPermission::CUSTOMER_VIEW,
                    ResellerPortalPermission::PAYMENT_COLLECT,
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('staff.login', 'collector1');

        $this->withToken($token)
            ->getJson('/api/v1/reseller/wallet/overview')
            ->assertOk()
            ->assertJsonStructure(['available_main', 'quota', 'transactions']);
    }

    public function test_partner_api_key_can_read_dashboard(): void
    {
        $reseller = Reseller::query()->create([
            'tenant_id' => 1,
            'name' => 'Key Partner',
            'code' => 'RSL-KEY',
            'franchise_type' => ResellerType::FRANCHISE,
            'commission_type' => 'percent',
            'commission_value' => 10,
            'is_active' => true,
            'api_access_enabled' => true,
            'portal_password' => Hash::make('x'),
            'portal_permissions' => ResellerPortalPermission::defaultsFor(ResellerType::FRANCHISE),
        ]);

        $plain = \App\Models\ResellerApiKey::generate($reseller, 'mobile')['plain'];

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->getJson('/api/v1/reseller/partner/dashboard')
            ->assertOk()
            ->assertJsonStructure(['metrics']);
    }
}
