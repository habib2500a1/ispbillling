<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Support\CustomerStatus;
use Illuminate\Database\Eloquent\Builder;

class ListTodaysCustomers extends ListFilteredCustomers
{
    protected static ?string $navigationLabel = "Today's renewals";

    protected static ?string $title = "Today's renewals";

    public static function getNavigationLabel(): string
    {
        return "Today's renewals";
    }

    protected function applyFilter(Builder $query): Builder
    {
        $billingDay = min(28, max(1, (int) today()->day));

        return $query
            ->where('billing_day', $billingDay)
            ->whereIn('status', [CustomerStatus::ACTIVE, CustomerStatus::EXPIRED, CustomerStatus::SUSPENDED]);
    }
}
