<?php

namespace App\Support;

use App\Filament\Pages\FiberPlantMap;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

final class NetworkMapSidebarRegistry
{
    public const GROUP_LABEL = 'Network';

    /**
     * @return list<array{key: string, label: string, icon: string, sort: int, url: string, active_routes: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'fiber_map',
                'label' => 'Network operations map',
                'icon' => 'heroicon-o-map',
                'sort' => 8,
                'url' => FiberPlantMap::getUrl(),
                'active_routes' => ['filament.admin.pages.fiber-plant-map'],
            ],
        ];
    }

    /**
     * @return array<NavigationItem>
     */
    public static function navigationItems(): array
    {
        if (Filament::getCurrentPanel() === null) {
            return [];
        }

        $items = [];

        foreach (self::definitions() as $entry) {
            if (! self::canSeeEntry($entry['key'])) {
                continue;
            }

            $items[] = NavigationItem::make($entry['label'])
                ->url($entry['url'])
                ->icon($entry['icon'])
                ->group(self::GROUP_LABEL)
                ->sort($entry['sort'])
                ->isActiveWhen(function () use ($entry): bool {
                    foreach ($entry['active_routes'] as $route) {
                        if (request()->routeIs($route)) {
                            return true;
                        }
                    }

                    return false;
                });
        }

        return $items;
    }

    public static function hasVisibleEntries(): bool
    {
        foreach (self::definitions() as $entry) {
            if (self::canSeeEntry($entry['key'])) {
                return true;
            }
        }

        return false;
    }

    public static function canSeeEntry(string $key): bool
    {
        return match ($key) {
            'fiber_map' => FiberPlantMap::canAccess(),
            default => false,
        };
    }
}
