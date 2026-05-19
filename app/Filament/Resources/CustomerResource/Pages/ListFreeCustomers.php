<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Support\CustomerStatus;
use App\Support\SubscriberType;
use Illuminate\Database\Eloquent\Builder;

class ListFreeCustomers extends ListFilteredCustomers
{
    protected static ?string $navigationLabel = 'Free clients';

    protected static ?string $title = 'Free / complimentary clients';

    public static function getNavigationLabel(): string
    {
        return 'Free clients';
    }

    protected function applyFilter(Builder $query): Builder
    {
        return $query->where('subscriber_type', SubscriberType::FREE)
            ->where('status', '!=', CustomerStatus::TERMINATED);
    }
}
