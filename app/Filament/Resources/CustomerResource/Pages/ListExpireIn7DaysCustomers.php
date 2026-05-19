<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource\Pages\Concerns\HasBillingAccountListPage;
use App\Support\CustomerStatus;
use Illuminate\Database\Eloquent\Builder;

class ListExpireIn7DaysCustomers extends ListFilteredCustomers
{
    use HasBillingAccountListPage;

    protected static ?string $navigationLabel = 'Expire in 7 days';

    protected static ?string $title = 'Expiring within 7 days';

    public static function getNavigationLabel(): string
    {
        return 'Expire in 7 days';
    }

    protected function applyFilter(Builder $query): Builder
    {
        $end = now()->addDays(7)->toDateString();

        return $query
            ->where('status', '!=', CustomerStatus::TERMINATED)
            ->whereNotNull('service_expires_at')
            ->whereDate('service_expires_at', '>=', now()->toDateString())
            ->whereDate('service_expires_at', '<=', $end);
    }
}
