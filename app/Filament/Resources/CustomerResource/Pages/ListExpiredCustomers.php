<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Support\CustomerStatus;
use Illuminate\Database\Eloquent\Builder;

class ListExpiredCustomers extends ListFilteredCustomers
{
    use Concerns\HasBillingAccountListPage;

    protected static ?string $navigationLabel = 'Expired accounts';

    protected static ?string $title = 'Expired accounts';

    public static function getNavigationLabel(): string
    {
        return 'Expired accounts';
    }

    protected function applyFilter(Builder $query): Builder
    {
        return $query->where('status', '!=', CustomerStatus::TERMINATED)
            ->where(function (Builder $q): void {
                $q->where('status', CustomerStatus::EXPIRED)
                    ->orWhere(function (Builder $q2): void {
                        $q2->whereNotNull('service_expires_at')
                            ->whereDate('service_expires_at', '<', now()->toDateString());
                    });
            });
    }
}
