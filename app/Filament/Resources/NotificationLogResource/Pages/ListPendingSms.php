<?php

namespace App\Filament\Resources\NotificationLogResource\Pages;

use App\Filament\Resources\NotificationLogResource;
use App\Filament\Resources\NotificationLogResource\Pages\Concerns\ListsSmsNotificationLogs;
use Filament\Resources\Pages\ListRecords;

class ListPendingSms extends ListRecords
{
    use ListsSmsNotificationLogs;

    protected static string $resource = NotificationLogResource::class;

    protected static ?string $title = 'Pending SMS';

    protected function smsStatusFilter(): ?string
    {
        return 'pending';
    }

    protected static ?string $navigationLabel = 'Pending SMS';
}
