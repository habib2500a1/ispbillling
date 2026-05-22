<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class PrintReportsHub extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-printer';

    protected static string $view = 'filament.pages.print-reports-hub';

    protected static ?string $navigationLabel = 'Print Reports';

    protected static ?string $title = 'Print Reports';

    protected static ?string $slug = 'print-reports';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return PaymentsReport::canAccess();
    }

    /**
     * @return list<array{label: string, hint: string, url: string, icon: string}>
     */
    public function getPrintablesProperty(): array
    {
        return [
            [
                'label' => 'Due report',
                'hint' => 'Outstanding invoices — print or save as PDF',
                'url' => DueReportPage::getUrl(['print' => 1]),
                'icon' => 'heroicon-o-exclamation-triangle',
            ],
            [
                'label' => 'Due report pro',
                'hint' => 'Aging buckets and detailed balances',
                'url' => DueReportProPage::getUrl(['print' => 1]),
                'icon' => 'heroicon-o-shield-exclamation',
            ],
            [
                'label' => 'Payments report',
                'hint' => 'Collections for selected period',
                'url' => PaymentsReport::getUrl(['print' => 1]),
                'icon' => 'heroicon-o-banknotes',
            ],
            [
                'label' => 'Bill money trail',
                'hint' => 'Where collections went · bills · wallet · expenses',
                'url' => BillingFundFlowReport::getUrl(['print' => 1]),
                'icon' => 'heroicon-o-arrows-right-left',
            ],
            [
                'label' => 'Area-wise clients',
                'hint' => 'Subscribers and dues by area',
                'url' => AreaWiseClientsReport::getUrl(['print' => 1]),
                'icon' => 'heroicon-o-map-pin',
            ],
            [
                'label' => 'Package-wise report',
                'hint' => 'Active subscribers per package',
                'url' => PackageWiseReportPage::getUrl(['print' => 1]),
                'icon' => 'heroicon-o-rectangle-stack',
            ],
            [
                'label' => 'BTRC DIS report',
                'hint' => 'Regulatory subscriber export',
                'url' => BtrcReport::getUrl(),
                'icon' => 'heroicon-o-document-arrow-down',
            ],
        ];
    }
}
