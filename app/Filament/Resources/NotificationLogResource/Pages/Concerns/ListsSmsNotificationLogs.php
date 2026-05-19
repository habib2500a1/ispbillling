<?php

namespace App\Filament\Resources\NotificationLogResource\Pages\Concerns;

use App\Support\NotificationChannel;
use Illuminate\Database\Eloquent\Builder;

trait ListsSmsNotificationLogs
{
    protected function smsStatusFilter(): ?string
    {
        return null;
    }

    public function getSubheading(): ?string
    {
        return 'SMS channel only · newest first.';
    }

    public function getTableQuery(): Builder
    {
        $query = parent::getTableQuery()
            ->where('channel', NotificationChannel::SMS);

        $status = $this->smsStatusFilter();
        if (filled($status)) {
            $query->where('status', $status);
        }

        return $query;
    }
}
