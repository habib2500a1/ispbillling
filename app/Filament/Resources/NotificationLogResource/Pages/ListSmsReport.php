<?php

namespace App\Filament\Resources\NotificationLogResource\Pages;

use App\Filament\Resources\NotificationLogResource;
use App\Filament\Resources\NotificationLogResource\Pages\Concerns\ListsSmsNotificationLogs;
use Filament\Resources\Pages\ListRecords;

class ListSmsReport extends ListRecords
{
    use ListsSmsNotificationLogs;

    protected static string $resource = NotificationLogResource::class;

    protected static ?string $title = 'SMS Report';

    protected static ?string $navigationLabel = 'SMS Report';
}
