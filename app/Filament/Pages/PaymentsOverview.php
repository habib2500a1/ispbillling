<?php

namespace App\Filament\Pages;
use App\Filament\Pages\Concerns\HidesHubNavigation;

use Filament\Pages\Page;

class PaymentsOverview extends Page
{
    use HidesHubNavigation;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static string $view = 'filament.pages.payments-overview';

    protected static ?string $navigationLabel = 'Payments hub';

    protected static ?string $title = 'Payment system';

    protected static ?string $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return auth()->check();
    }
}
