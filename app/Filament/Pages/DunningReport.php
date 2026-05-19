<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Services\Billing\DunningReportService;
use Filament\Pages\Page;

class DunningReport extends Page
{
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static string $view = 'filament.pages.dunning-report';

    protected static ?string $navigationLabel = 'Dunning report';

    protected static ?string $title = 'Dunning SMS / WhatsApp report';

    protected static ?string $navigationGroup = 'Billing';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([
            'super-admin',
            'isp-admin',
            'isp-manager',
        ]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function getReport(): array
    {
        return app(DunningReportService::class)->snapshot();
    }
}
