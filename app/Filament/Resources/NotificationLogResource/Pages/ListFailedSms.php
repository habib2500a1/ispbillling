<?php

namespace App\Filament\Resources\NotificationLogResource\Pages;

use App\Filament\Resources\NotificationLogResource;
use App\Filament\Resources\NotificationLogResource\Pages\Concerns\ListsSmsNotificationLogs;
use Filament\Resources\Pages\ListRecords;

class ListFailedSms extends ListRecords
{
    use ListsSmsNotificationLogs;

    protected static string $resource = NotificationLogResource::class;

    protected static ?string $title = 'Failed SMS';

    protected function smsStatusFilter(): ?string
    {
        return 'failed';
    }

    protected static ?string $navigationLabel = 'Failed SMS';
}
