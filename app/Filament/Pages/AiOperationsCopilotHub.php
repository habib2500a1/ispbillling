<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Services\Ai\AiIntentCatalog;
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
        // Lightweight shell — KPIs/alerts load once via JS (avoids duplicate heavy queries on Livewire mount).
        $this->dashboard = [
            'summary' => [
                'collected_today' => 0,
                'open_tickets' => 0,
                'customers_offline' => 0,
                'active_faults' => 0,
                'network_health' => 0,
                'revenue_trend_pct' => 0,
            ],
            'alerts' => [],
            'recommendations' => [],
            'chips' => array_slice(app(AiIntentCatalog::class)->quickChips(), 0, 12),
        ];
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
