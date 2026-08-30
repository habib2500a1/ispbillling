<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CollectionReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_collection_report_page_is_responsive_for_staff(): void
    {
        Role::findOrCreate('Super Admin', 'web');
        Permission::findOrCreate('payment-collection-report', 'web');
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $html = $this->actingAs($user)
            ->get(route('collection-report.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('cr-page', $html);
        $this->assertStringContainsString('col-12 col-sm-6 col-lg-3', $html);
        $this->assertStringContainsString('cr-table-wrap', $html);
        $this->assertStringContainsString('min-height:44px', $html);
        $this->assertStringContainsString('collection-report-form', $html);
    }
}
