<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\InventorySidebarRegistry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventorySidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_inventory_pro_sidebar_entries(): void
    {
        Role::findOrCreate('super-admin');

        $user = User::factory()->create();
        $user->assignRole('super-admin');

        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertTrue(InventorySidebarRegistry::hasVisibleEntries());

        $labels = array_map(
            fn ($item) => $item->getLabel(),
            InventorySidebarRegistry::navigationItems(),
        );

        $this->assertContains('Inventory center', $labels);
        $this->assertContains('Warehouses', $labels);
        $this->assertContains('New sale (POS)', $labels);

        foreach (InventorySidebarRegistry::navigationItems() as $item) {
            $this->assertSame(InventorySidebarRegistry::GROUP_LABEL, $item->getGroup());
        }
    }
}
