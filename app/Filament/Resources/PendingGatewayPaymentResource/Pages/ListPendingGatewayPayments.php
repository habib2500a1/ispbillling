<?php

namespace App\Filament\Resources\PendingGatewayPaymentResource\Pages;

use App\Filament\Resources\PendingGatewayPaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListPendingGatewayPayments extends ListRecords
{
    protected static string $resource = PendingGatewayPaymentResource::class;
}
