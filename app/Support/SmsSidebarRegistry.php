<?php

namespace App\Support;

use App\Filament\Pages\BroadcastOutage;
use App\Filament\Pages\BulkSmsCampaign;
use App\Filament\Pages\ManageNotifications;
use App\Filament\Pages\NotificationsHub;
use App\Filament\Pages\SendSms;
use App\Filament\Resources\SmsTemplateResource;
use App\Filament\Pages\SmsGatewaySetup;
use App\Filament\Resources\NotificationLogResource;
use App\Filament\Resources\SmsDeliveryReportResource;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

final class SmsSidebarRegistry
{
    /**
     * @return list<array{key: string, label: string, icon: string, sort: int, url: string, active_routes: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'notifications_hub',
                'label' => 'Notifications hub',
                'icon' => 'heroicon-o-bell-alert',
                'sort' => 0,
                'url' => NotificationsHub::getUrl(),
                'active_routes' => ['filament.admin.pages.notifications-hub'],
            ],
            [
                'key' => 'send_sms',
                'label' => 'Send SMS',
                'icon' => 'heroicon-o-chat-bubble-left',
                'sort' => 1,
                'url' => SendSms::getUrl(),
                'active_routes' => ['filament.admin.pages.send-sms'],
            ],
            [
                'key' => 'send_bulk',
                'label' => 'Send SMS Bulk',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'sort' => 2,
                'url' => BulkSmsCampaign::getUrl(),
                'active_routes' => ['filament.admin.pages.bulk-sms-campaign'],
            ],
            [
                'key' => 'sms_report',
                'label' => 'SMS Report',
                'icon' => 'heroicon-o-chart-bar',
                'sort' => 3,
                'url' => NotificationLogResource::getUrl('sms-report'),
                'active_routes' => ['filament.admin.resources.notification-logs.sms-report'],
            ],
            [
                'key' => 'delivered',
                'label' => 'Delivered SMS',
                'icon' => 'heroicon-o-envelope-open',
                'sort' => 4,
                'url' => NotificationLogResource::getUrl('delivered'),
                'active_routes' => ['filament.admin.resources.notification-logs.delivered'],
            ],
            [
                'key' => 'pending',
                'label' => 'Pending SMS',
                'icon' => 'heroicon-o-clock',
                'sort' => 5,
                'url' => NotificationLogResource::getUrl('pending'),
                'active_routes' => ['filament.admin.resources.notification-logs.pending'],
            ],
            [
                'key' => 'failed',
                'label' => 'Failed SMS',
                'icon' => 'heroicon-o-x-circle',
                'sort' => 6,
                'url' => NotificationLogResource::getUrl('failed'),
                'active_routes' => ['filament.admin.resources.notification-logs.failed'],
            ],
            [
                'key' => 'templates',
                'label' => 'SMS Templates',
                'icon' => 'heroicon-o-document-text',
                'sort' => 7,
                'url' => SmsTemplateResource::getUrl(),
                'active_routes' => [
                    'filament.admin.resources.sms-templates.index',
                    'filament.admin.resources.sms-templates.edit',
                ],
            ],
            [
                'key' => 'broadcast_outage',
                'label' => 'Broadcast outage',
                'icon' => 'heroicon-o-megaphone',
                'sort' => 8,
                'url' => BroadcastOutage::getUrl(),
                'active_routes' => ['filament.admin.pages.broadcast-outage'],
            ],
            [
                'key' => 'dlr',
                'label' => 'SMS delivery (DLR)',
                'icon' => 'heroicon-o-check-badge',
                'sort' => 9,
                'url' => SmsDeliveryReportResource::getUrl(),
                'active_routes' => ['filament.admin.resources.sms-delivery-reports.index'],
            ],
            [
                'key' => 'gateway_tester',
                'label' => 'Gateway Tester',
                'icon' => 'heroicon-o-globe-alt',
                'sort' => 10,
                'url' => SmsGatewaySetup::getUrl(),
                'active_routes' => ['filament.admin.pages.sms-gateway'],
            ],
            [
                'key' => 'notification_settings',
                'label' => 'Gateway & events',
                'icon' => 'heroicon-o-cog-6-tooth',
                'sort' => 11,
                'url' => ManageNotifications::getUrl(),
                'active_routes' => ['filament.admin.pages.manage-notifications'],
            ],
        ];
    }

    /**
     * @return array<NavigationItem>
     */
    public static function navigationItems(): array
    {
        $panel = Filament::getCurrentPanel();
        if ($panel === null) {
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
                ->group('SMS Service')
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

    public static function canSeeEntry(string $key): bool
    {
        return match ($key) {
            'notifications_hub' => NotificationsHub::canAccess(),
            'send_sms' => SendSms::canAccess(),
            'send_bulk' => BulkSmsCampaign::canAccess(),
            'sms_report', 'delivered', 'pending', 'failed' => NotificationLogResource::canViewAny(),
            'templates' => SmsTemplateResource::canViewAny(),
            'broadcast_outage' => BroadcastOutage::canAccess(),
            'dlr' => SmsDeliveryReportResource::canViewAny(),
            'gateway_tester' => SmsGatewaySetup::canAccess(),
            'notification_settings' => ManageNotifications::canAccess(),
            default => false,
        };
    }
}
