<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Services\Ai\AiOperationsOrchestrator;
use App\Support\Rbac\StaffCapability;
use Filament\Pages\Page;

class AiOperationsCopilotHub extends Page
{
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static string $view = 'filament.pages.ai-operations-copilot';

    protected static ?string $navigationLabel = 'AI Copilot';

    protected static ?string $title = '';

    protected static ?string $slug = 'ai-copilot';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed> */
    public array $dashboard = [];

    public bool $showAlerts = false;

    public function mount(): void
    {
        $this->refreshDashboard();
    }

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }

    public function getExtraBodyAttributes(): array
    {
        return ['class' => 'ai-copilot-module'];
    }

    public function refreshDashboard(): void
    {
        $this->dashboard = app(AiOperationsOrchestrator::class)->dashboard();
    }

    public function toggleAlerts(): void
    {
        $this->showAlerts = ! $this->showAlerts;
    }

    public static function canAccess(): bool
    {
        $cap = StaffCapability::for(auth()->user());

        return $cap->canReports()
            || $cap->canBilling()
            || $cap->canNetwork()
            || $cap->canSupport();
    }
}
