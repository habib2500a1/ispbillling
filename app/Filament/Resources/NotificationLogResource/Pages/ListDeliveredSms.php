<?php

namespace App\Filament\Resources\NotificationLogResource\Pages;

use App\Filament\Resources\NotificationLogResource;
use App\Filament\Resources\NotificationLogResource\Pages\Concerns\ListsSmsNotificationLogs;
use Filament\Resources\Pages\ListRecords;

class ListDeliveredSms extends ListRecords
{
    use ListsSmsNotificationLogs;

    protected static string $resource = NotificationLogResource::class;

    protected static ?string $title = 'Delivered SMS';

    protected function smsStatusFilter(): ?string
    {
        return 'sent';
    }

    protected static ?string $navigationLabel = 'Delivered SMS';
}
