<?php

namespace App\Filament\Pages;

use App\Services\Dashboard\DashboardMetricsService;
use App\Support\CompanyBranding;
use Filament\Pages\Page;

class NocWall extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-tv';

    protected static string $view = 'filament.pages.noc-wall';

    protected static ?string $title = 'NOC wall';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $layout = 'filament.layouts.noc-wall';

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function companyName(): string
    {
        return CompanyBranding::name();
    }

    public function companyLogoUrl(): ?string
    {
        return CompanyBranding::logoUrl();
    }

    public function companyInitial(): string
    {
        return CompanyBranding::brandInitial();
    }

    /**
     * @return array<string, mixed>
     */
    public function getWallData(): array
    {
        return app(DashboardMetricsService::class)->nocWallPayload();
    }
}
