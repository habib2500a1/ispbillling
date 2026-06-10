<?php

namespace App\Support;

use App\Filament\Pages\AiOperationsCopilotHub;
use App\Filament\Pages\FaultManagementHub;
use App\Filament\Pages\FieldTechnicianCenter;
use App\Filament\Pages\IspOsHub;
use App\Filament\Pages\NocWall;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

final class IspOsSidebarRegistry
{
    public const GROUP_LABEL = NetworkMapSidebarRegistry::GROUP_LABEL;

    /**
     * @return list<array{key: string, label: string, icon: string, sort: int|float, url: string, active_routes: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'ai_copilot',
                'label' => 'AI Copilot',
                'icon' => 'heroicon-o-sparkles',
                'sort' => 20,
                'url' => AiOperationsCopilotHub::getUrl(),
                'active_routes' => ['filament.admin.pages.ai-copilot'],
            ],
            [
                'key' => 'isp_os_hub',
                'label' => 'ISP OS center',
                'icon' => 'heroicon-o-command-line',
                'sort' => 21,
                'url' => IspOsHub::getUrl(),
                'active_routes' => ['filament.admin.pages.isp-os'],
            ],
            [
                'key' => 'fault_center',
                'label' => 'Fault center',
                'icon' => 'heroicon-o-exclamation-triangle',
                'sort' => 22,
                'url' => FaultManagementHub::getUrl(),
                'active_routes' => ['filament.admin.pages.fault-center'],
            ],
            [
                'key' => 'field_technicians',
                'label' => 'Field technicians',
                'icon' => 'heroicon-o-wrench-screwdriver',
                'sort' => 23,
                'url' => FieldTechnicianCenter::getUrl(),
                'active_routes' => ['filament.admin.pages.field-technicians'],
            ],
            [
                'key' => 'noc_wall',
                'label' => 'NOC wall',
                'icon' => 'heroicon-o-tv',
                'sort' => 24,
                'url' => NocWall::getUrl(),
                'active_routes' => ['filament.admin.pages.noc-wall'],
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
            'ai_copilot' => AiOperationsCopilotHub::canAccess(),
            'isp_os_hub' => IspOsHub::canAccess(),
            'fault_center' => FaultManagementHub::canAccess(),
            'field_technicians' => FieldTechnicianCenter::canAccess(),
            'noc_wall' => NocWall::canAccess(),
            default => false,
        };
    }
}
