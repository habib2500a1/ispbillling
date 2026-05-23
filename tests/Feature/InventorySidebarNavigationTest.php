<?php

namespace Tests\Feature;

use App\Filament\Navigation\IspNavigationManager;
use App\Filament\Navigation\IspSidebarNavigation;
use App\Filament\Resources\OltResource;
use App\Models\User;
use App\Support\InventorySidebarRegistry;
use App\Support\OltSidebarRegistry;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventorySidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_keeps_inventory_pro_and_moves_olt_to_olt_tools(): void
    {
        Role::findOrCreate('super-admin');

        $user = User::factory()->create();
        $user->assignRole('super-admin');

        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $items = IspSidebarNavigation::allNavigationItems();

        $labels = array_map(fn ($item) => (string) $item->getLabel(), $items);
        $groups = array_map(fn ($item) => (string) ($item->getGroup() ?? ''), $items);

        $this->assertContains('Inventory center', $labels);
        $this->assertContains(InventorySidebarRegistry::GROUP_LABEL, $groups);
        $this->assertContains('OLT', $labels);
        $this->assertContains(OltSidebarRegistry::GROUP_LABEL, $groups);

        $oltListUrl = OltResource::getUrl();
        $inventoryOltLinks = array_filter(
            $items,
            fn ($item) => (string) ($item->getGroup() ?? '') === InventorySidebarRegistry::GROUP_LABEL
                && (string) $item->getUrl() === $oltListUrl,
        );

        $this->assertSame([], array_values($inventoryOltLinks));

        $this->assertTrue(OltSidebarRegistry::hasVisibleEntries());
        $this->assertTrue(InventorySidebarRegistry::hasVisibleEntries());
    }

    public function test_post_process_strips_olts_label_from_inventory_pro_group(): void
    {
        $misplaced = NavigationItem::make('OLTs')
            ->url(OltResource::getUrl())
            ->group(InventorySidebarRegistry::GROUP_LABEL);

        $groups = IspSidebarNavigation::postProcessNavigationGroups([
            NavigationGroup::make(InventorySidebarRegistry::GROUP_LABEL)
                ->items([$misplaced, NavigationItem::make('Vendors')->url('/admin/vendors')]),
        ]);

        $inventory = collect($groups)->first(
            fn (NavigationGroup $g) => $g->getLabel() === InventorySidebarRegistry::GROUP_LABEL,
        );

        $this->assertNotNull($inventory);
        $labels = collect($inventory->getItems())->map(fn ($i) => (string) $i->getLabel())->all();
        $this->assertNotContains('OLTs', $labels);
        $this->assertContains('Vendors', $labels);
    }

    public function test_navigation_manager_filters_inventory_olt_on_render(): void
    {
        Role::findOrCreate('super-admin');
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $groups = app(IspNavigationManager::class)->get();
        $labels = [];

        foreach ($groups as $group) {
            if ($group->getLabel() === InventorySidebarRegistry::GROUP_LABEL) {
                foreach ($group->getItems() as $item) {
                    $labels[] = (string) $item->getLabel();
                }
            }
        }

        $this->assertNotContains('OLTs', $labels);

        $groupLabels = array_map(fn (NavigationGroup $g) => (string) $g->getLabel(), $groups);
        $this->assertContains(OltSidebarRegistry::GROUP_LABEL, $groupLabels);
    }
}
