<?php

namespace App\Filament\Resources\VendorPaymentResource\Pages;

use App\Filament\Resources\VendorPaymentResource;
use App\Services\Accounting\VendorPaymentService;
use Filament\Resources\Pages\CreateRecord;

class CreateVendorPayment extends CreateRecord
{
    protected static string $resource = VendorPaymentResource::class;

    protected function afterCreate(): void
    {
        app(VendorPaymentService::class)->recordPayment($this->record);
    }
}
