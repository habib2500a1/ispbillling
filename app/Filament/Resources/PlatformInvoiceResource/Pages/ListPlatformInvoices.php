<?php

namespace App\Filament\Resources\PlatformInvoiceResource\Pages;

use App\Filament\Resources\PlatformInvoiceResource;
use Filament\Resources\Pages\ListRecords;

class ListPlatformInvoices extends ListRecords
{
    protected static string $resource = PlatformInvoiceResource::class;

    protected static ?string $title = 'Platform invoices';

    public function getSubheading(): ?string
    {
        return 'Monthly SaaS software bills for each ISP tenant — auto-generated on subscription bill day.';
    }
}
