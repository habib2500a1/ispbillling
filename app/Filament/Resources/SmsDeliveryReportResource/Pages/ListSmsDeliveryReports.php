<?php

namespace App\Filament\Resources\SmsDeliveryReportResource\Pages;

use App\Filament\Resources\SmsDeliveryReportResource;
use App\Support\KhudeBartaUrls;
use Filament\Resources\Pages\ListRecords;

class ListSmsDeliveryReports extends ListRecords
{
    protected static string $resource = SmsDeliveryReportResource::class;

    protected static ?string $title = 'SMS delivery reports (DLR)';

    public function getSubheading(): ?string
    {
        return 'KhudeBarta DLR callback: '.KhudeBartaUrls::dlrCallbackUrl();
    }
}
