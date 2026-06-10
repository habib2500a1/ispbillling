<?php

namespace App\Support;

use App\Filament\Pages\HorizonQueueMonitor;
use App\Filament\Pages\ManageAppSettings;
use App\Filament\Pages\ManagePlatformBackups;
use App\Filament\Pages\ManageStaffSecurity;
use App\Filament\Pages\PermissionMatrix;
use App\Filament\Pages\SecurityDashboard;
use App\Filament\Pages\IspSetupCenter;
use App\Filament\Pages\TenantOrganizationCenter;
use App\Filament\Pages\SystemErrorLogsPage;
use App\Filament\Pages\TwoFactorSetup;
use App\Filament\Pages\MobileAppsHub;
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
                'key' => 'staff_control',
                'label' => 'Organization',
                'icon' => 'heroicon-o-building-library',
                'sort' => 0,
                'url' => TenantOrganizationCenter::getUrl(),
                'active_routes' => [
                    'filament.admin.pages.tenant-organization',
                    'filament.admin.pages.staff-control-hub',
                ],
            ],
            [
                'key' => 'isp_setup',
                'label' => 'ISP setup',
                'icon' => 'heroicon-o-wrench-screwdriver',
                'sort' => 1,
                'url' => IspSetupCenter::getUrl(),
                'active_routes' => ['filament.admin.pages.isp-setup'],
            ],
            [
                'key' => 'users',
                'label' => 'Users',
                'icon' => 'heroicon-o-user-group',
                'sort' => 2,
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
                'key' => 'error_logs',
                'label' => 'Error logs',
                'icon' => 'heroicon-o-exclamation-triangle',
                'sort' => 4.5,
                'url' => SystemErrorLogsPage::getUrl(),
                'active_routes' => ['filament.admin.pages.system-error-logs'],
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
                'key' => 'backups-google',
                'label' => 'Google Drive backup',
                'icon' => 'heroicon-o-cloud',
                'sort' => 5.5,
                'url' => ManagePlatformBackups::getUrl(['tab' => 'google']),
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
                'key' => 'queue_monitor',
                'label' => 'Queue monitor',
                'icon' => 'heroicon-o-queue-list',
                'sort' => 73,
                'url' => HorizonQueueMonitor::getUrl(),
                'active_routes' => ['filament.admin.pages.queue-monitor'],
            ],
            [
                'key' => 'staff_security',
                'label' => 'Staff security',
                'icon' => 'heroicon-o-lock-closed',
                'sort' => 8.5,
                'url' => ManageStaffSecurity::getUrl(),
                'active_routes' => ['filament.admin.pages.manage-staff-security'],
            ],
            [
                'key' => 'two_factor',
                'label' => 'Two-factor setup',
                'icon' => 'heroicon-o-device-phone-mobile',
                'sort' => 8.6,
                'url' => TwoFactorSetup::getUrl(),
                'active_routes' => ['filament.admin.pages.two-factor-setup'],
            ],
            [
                'key' => 'mobile_apps',
                'label' => 'Mobile apps',
                'icon' => 'heroicon-o-device-phone-mobile',
                'sort' => 8.7,
                'url' => MobileAppsHub::getUrl(),
                'active_routes' => ['filament.admin.pages.mobile-apps-hub'],
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
            'staff_control' => TenantOrganizationCenter::canAccess(),
            'isp_setup' => IspSetupCenter::canAccess(), // same rules as page
            'users' => UserResource::canViewAny(),
            'roles' => RoleResource::canViewAny(),
            'permissions' => PermissionMatrix::canAccess(),
            'activity' => ActivityLogResource::canViewAny(),
            'error_logs' => SystemErrorLogsPage::canAccess(),
            'backups' => ManagePlatformBackups::canAccess(),
            'backups-google' => ManagePlatformBackups::canAccess(),
            'integrations' => ManageAppSettings::canAccess(),
            'automatic' => AutomaticProcessResource::canViewAny(),
            'queue_monitor' => HorizonQueueMonitor::canAccess(),
            'staff_security' => ManageStaffSecurity::canAccess(),
            'two_factor' => TwoFactorSetup::canAccess(),
            'mobile_apps' => MobileAppsHub::canAccess(),
            'security' => SecurityDashboard::canAccess(),
            default => false,
        };
    }
}
