<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource\Pages\Concerns\HasBillingAccountListPage;
use Illuminate\Database\Eloquent\Builder;

class ListTodaysCustomers extends ListFilteredCustomers
{
    use HasBillingAccountListPage;

    protected static ?string $navigationLabel = "Today's clients";

    protected static ?string $title = "Today's new clients";

    public static function getNavigationLabel(): string
    {
        return "Today's clients";
    }

    protected function applyFilter(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }
}
