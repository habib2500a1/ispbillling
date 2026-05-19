<?php

namespace App\Filament\Resources\BandwidthClientPaymentResource\Pages;

use App\Filament\Resources\BandwidthClientPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBandwidthClientPayments extends ListRecords
{
    protected static string $resource = BandwidthClientPaymentResource::class;

    protected static ?string $title = 'Payment history';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Record payment'),
        ];
    }
}
