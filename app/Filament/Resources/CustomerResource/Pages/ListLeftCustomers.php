<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Support\CustomerStatus;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListLeftCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected static ?string $navigationLabel = 'Left subscribers';

    protected static ?string $title = 'Left subscribers';

    public static function getNavigationLabel(): string
    {
        return 'Left subscribers';
    }

    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()?->where('status', CustomerStatus::TERMINATED);
    }
}
