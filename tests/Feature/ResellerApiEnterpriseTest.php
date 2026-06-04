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
}
