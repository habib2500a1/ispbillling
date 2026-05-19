<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Support\CustomerStatus;
use Illuminate\Database\Eloquent\Builder;

class ListSuspendedCustomers extends ListFilteredCustomers
{
    protected static ?string $navigationLabel = 'Suspended';

    protected static ?string $title = 'Suspended subscribers';

    public static function getNavigationLabel(): string
    {
        return 'Suspended';
    }

    protected function applyFilter(Builder $query): Builder
    {
        return $query->where('status', CustomerStatus::SUSPENDED);
    }
}
