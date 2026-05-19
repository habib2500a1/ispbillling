<?php

namespace App\Filament\Resources\NotificationLogResource\Pages;

use App\Filament\Pages\NotificationsHub;
use App\Filament\Resources\NotificationLogResource;
use Filament\Resources\Pages\ListRecords;

class ListNotificationLogs extends ListRecords
{
    protected static string $resource = NotificationLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getSubheading(): ?string
    {
        return 'Delivery history across SMS, email, WhatsApp, and Telegram.';
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function getBreadcrumbs(): array
    {
        return [
            NotificationsHub::getUrl() => 'Notifications',
            static::getUrl() => 'Delivery log',
        ];
    }
}
