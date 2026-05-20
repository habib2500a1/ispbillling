<?php

namespace App\Support;

use App\Filament\Pages\ManageAppSettings;
use App\Filament\Pages\ManagePlatformBackups;
use App\Filament\Pages\PermissionMatrix;
use App\Filament\Pages\SecurityDashboard;
use App\Filament\Pages\StaffControlHub;
use App\Filament\Resources\ActivityLogResource;
use App\Filament\Resources\AutomaticProcessResource;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\UserResource;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

final class SystemSidebarRegistry
{
    /**
     * @return list<array{key: string, label: string, icon: string, sort: int, url: string, active_routes: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'users',
                'label' => 'Users',
                'icon' => 'heroicon-o-user-group',
                'sort' => 1,
                'url' => UserResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.users.index',
                    'filament.admin.resources.users.create',
                    'filament.admin.resources.users.edit',
                ],
            ],
            [
                'key' => 'roles',
                'label' => 'Roles',
                'icon' => 'heroicon-o-shield-check',
                'sort' => 2,
                'url' => RoleResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.roles.index',
                    'filament.admin.resources.roles.create',
                    'filament.admin.resources.roles.edit',
                ],
            ],
            [
                'key' => 'permissions',
                'label' => 'Permission matrix',
                'icon' => 'heroicon-o-table-cells',
                'sort' => 3,
                'url' => PermissionMatrix::getUrl(),
                'active_routes' => ['filament.admin.pages.permission-matrix'],
            ],
            [
                'key' => 'activity',
                'label' => 'Activity log',
                'icon' => 'heroicon-o-clipboard-document-list',
                'sort' => 4,
                'url' => ActivityLogResource::getUrl(),
                'active_routes' => ['filament.admin.resources.activity-logs.index'],
            ],
            [
                'key' => 'backups',
                'label' => 'Backup & restore',
                'icon' => 'heroicon-o-circle-stack',
                'sort' => 5,
                'url' => ManagePlatformBackups::getUrl(),
                'active_routes' => ['filament.admin.pages.manage-platform-backups'],
            ],
            [
                'key' => 'integrations',
                'label' => 'Integrations',
                'icon' => 'heroicon-o-puzzle-piece',
                'sort' => 6,
                'url' => ManageAppSettings::getUrl(),
                'active_routes' => ['filament.admin.pages.manage-app-settings'],
            ],
            [
                'key' => 'automatic',
                'label' => 'Automatic process',
                'icon' => 'heroicon-o-cpu-chip',
                'sort' => 7,
                'url' => AutomaticProcessResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.automatic-processes.index',
                    'filament.admin.resources.automatic-processes.edit',
                ],
            ],
            [
                'key' => 'security',
                'label' => 'Security dashboard',
                'icon' => 'heroicon-o-lock-closed',
                'sort' => 8,
                'url' => SecurityDashboard::getUrl(),
                'active_routes' => ['filament.admin.pages.security-dashboard'],
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
                ->group('System')
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
            'users' => UserResource::canViewAny(),
            'roles' => RoleResource::canViewAny(),
            'permissions' => PermissionMatrix::canAccess(),
            'activity' => ActivityLogResource::canViewAny(),
            'backups' => ManagePlatformBackups::canAccess(),
            'integrations' => ManageAppSettings::canAccess(),
            'automatic' => AutomaticProcessResource::canViewAny(),
            'security' => SecurityDashboard::canAccess(),
            default => false,
        };
    }
}
