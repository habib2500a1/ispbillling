<?php

namespace Tests\Unit;

use App\Filament\Navigation\IspSidebarNavigation;
use Filament\Navigation\NavigationItem;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SidebarNavigationDedupeTest extends TestCase
{
    public function test_prefers_olt_group_for_duplicate_urls(): void
    {
        $network = NavigationItem::make('OLTs')
            ->url('/admin/olts')
            ->group('Network');
        $olt = NavigationItem::make('OLT list')
            ->url('/admin/olts')
            ->group(\App\Support\OltSidebarRegistry::GROUP_LABEL);

        $method = new ReflectionMethod(IspSidebarNavigation::class, 'dedupeNavigationItems');
        $method->setAccessible(true);

        /** @var array<NavigationItem> $deduped */
        $deduped = $method->invoke(null, [$network, $olt]);

        $this->assertCount(1, $deduped);
        $this->assertSame('OLT list', $deduped[0]->getLabel());
        $this->assertSame(\App\Support\OltSidebarRegistry::GROUP_LABEL, $deduped[0]->getGroup());
    }
}
