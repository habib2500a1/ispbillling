<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Support\SubscriberType;
use Illuminate\Database\Eloquent\Builder;

class ListVipCustomers extends ListFilteredCustomers
{
    protected static ?string $navigationLabel = 'VIP clients';

    protected static ?string $title = 'VIP subscribers';

    public static function getNavigationLabel(): string
    {
        return 'VIP clients';
    }

    protected function applyFilter(Builder $query): Builder
    {
        return $query->where('subscriber_type', SubscriberType::VIP)
            ->where('status', '!=', CustomerStatus::TERMINATED);
    }
}
