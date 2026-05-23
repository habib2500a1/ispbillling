<?php

namespace Tests\Unit;

use App\Filament\Navigation\IspSidebarNavigation;
use Filament\Navigation\NavigationItem;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SidebarNavigationDedupeTest extends TestCase
{
    public function test_prefers_olt_tools_group_for_duplicate_urls(): void
    {
        $inventory = NavigationItem::make('OLTs')
            ->url('/admin/olts')
            ->group('Inventory Pro');
        $oltTools = NavigationItem::make('OLT manage')
            ->url('/admin/olts')
            ->group('OLT & Tools');

        $method = new ReflectionMethod(IspSidebarNavigation::class, 'dedupeNavigationItems');
        $method->setAccessible(true);

        /** @var array<NavigationItem> $deduped */
        $deduped = $method->invoke(null, [$inventory, $oltTools]);

        $this->assertCount(1, $deduped);
        $this->assertSame('OLT manage', $deduped[0]->getLabel());
        $this->assertSame('OLT & Tools', $deduped[0]->getGroup());
    }
}
