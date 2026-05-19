<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasRoleDashboard;
use App\Services\Dashboard\AiAnalyticsService;
use Filament\Pages\Page;

class AiAnalyticsDashboard extends Page
{
    use HasRoleDashboard;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static string $view = 'filament.pages.ai-analytics-dashboard';

    protected static ?string $title = 'AI analytics';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function getInsights(): array
    {
        return app(AiAnalyticsService::class)->insights();
    }
}
