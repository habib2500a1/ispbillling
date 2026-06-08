<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasRoleDashboard;
use Filament\Pages\Page;

/**
 * Legacy route — unified into AI Operations Copilot.
 */
class AiAnalyticsDashboard extends Page
{
    use HasRoleDashboard;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static string $view = 'filament.pages.ai-analytics-dashboard';

    protected static ?string $title = 'AI analytics';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $this->redirect(AiOperationsCopilotHub::getUrl(), navigate: true);
    }

    public static function canAccess(): bool
    {
        return static::staff()->canReports();
    }
}
