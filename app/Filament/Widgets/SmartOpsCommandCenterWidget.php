<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\BillCollectionDesk;
use App\Filament\Resources\AutomaticProcessResource;
use App\Filament\Resources\InvoiceResource;
use App\Services\Dashboard\DashboardMetricsService;
use App\Support\KhudeBartaUrls;
use Filament\Widgets\Widget;

class SmartOpsCommandCenterWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.widgets.smart-ops-command-center';

    protected static ?int $sort = -9;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '60s';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $data = app(DashboardMetricsService::class)->commandCenterSnapshot();

        return array_merge($data, [
            'links' => [
                'collection' => BillCollectionDesk::getUrl(),
                'invoices' => InvoiceResource::getUrl('index'),
                'automation' => AutomaticProcessResource::getUrl('index'),
            ],
            'khudebarta_dlr_url' => KhudeBartaUrls::dlrCallbackUrl(),
            'sms_provider' => (string) config('notifications.sms.provider', ''),
        ]);
    }
}
