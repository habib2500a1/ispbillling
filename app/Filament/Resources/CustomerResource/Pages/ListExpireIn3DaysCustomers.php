<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource\Pages\Concerns\HasBillingAccountListPage;
use App\Support\CustomerStatus;
use Illuminate\Database\Eloquent\Builder;

class ListExpireIn3DaysCustomers extends ListFilteredCustomers
{
    use HasBillingAccountListPage;

    protected static ?string $navigationLabel = 'Expire in 3 days';

    protected static ?string $title = 'Expiring within 3 days';

    public static function getNavigationLabel(): string
    {
        return 'Expire in 3 days';
    }

    protected function applyFilter(Builder $query): Builder
    {
        $end = now()->addDays(3)->toDateString();

        return $query
            ->where('status', '!=', CustomerStatus::TERMINATED)
            ->whereNotNull('service_expires_at')
            ->whereDate('service_expires_at', '>=', now()->toDateString())
            ->whereDate('service_expires_at', '<=', $end);
    }
}
