<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    /**
     * @return array<string, string|bool>
     */
    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'isp-billing-module isp-billing-invoice-detail',
        ];
    }
}
