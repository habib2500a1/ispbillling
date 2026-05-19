<?php

namespace App\Support;

use App\Filament\Pages\ManageCompanySetup;
use App\Filament\Pages\ManageMovieServerList;
use App\Filament\Pages\ManagePortalMarquee;
use App\Filament\Pages\ManagePortalNotices;
use App\Filament\Pages\ManagePortalSettings;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

final class SettingsSidebarRegistry
{
    /**
     * @return list<array{key: string, label: string, icon: string, sort: int, url: string, active_routes: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'app_settings',
                'label' => 'App Settings',
                'icon' => 'heroicon-o-adjustments-horizontal',
                'sort' => 1,
                'url' => ManagePortalSettings::getUrl(),
                'active_routes' => ['filament.admin.pages.app-settings'],
            ],
            [
                'key' => 'portal_notices',
                'label' => 'Portal Notices',
                'icon' => 'heroicon-o-megaphone',
                'sort' => 2,
                'url' => ManagePortalNotices::getUrl(),
                'active_routes' => ['filament.admin.pages.portal-notices'],
            ],
            [
                'key' => 'movie_servers',
                'label' => 'Movie Server List',
                'icon' => 'heroicon-o-film',
                'sort' => 3,
                'url' => ManageMovieServerList::getUrl(),
                'active_routes' => ['filament.admin.pages.movie-server-list'],
            ],
            [
                'key' => 'portal_marquee',
                'label' => 'Portal Marquee',
                'icon' => 'heroicon-o-bars-3-bottom-left',
                'sort' => 4,
                'url' => ManagePortalMarquee::getUrl(),
                'active_routes' => ['filament.admin.pages.portal-marquee'],
            ],
            [
                'key' => 'company_info',
                'label' => 'Company Info',
                'icon' => 'heroicon-o-building-office-2',
                'sort' => 5,
                'url' => ManageCompanySetup::getUrl(),
                'active_routes' => ['filament.admin.pages.company-setup'],
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
            $items[] = NavigationItem::make($entry['label'])
                ->url($entry['url'])
                ->icon($entry['icon'])
                ->group('Settings')
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
}
