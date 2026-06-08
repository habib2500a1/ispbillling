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

    public string $userQuery = '';

    /** @var array<string, mixed> */
    public array $aiSession = [];

    /** @var list<array<string, mixed>> */
    public array $messages = [];

    /** @var array<string, mixed> */
    public array $dashboard = [];

    public bool $showAlerts = false;

    public function mount(): void
    {
        $this->refreshDashboard();
        $this->messages[] = [
            'role' => 'assistant',
            'text' => 'ISP Operations Copilot ready. Ask about billing, NOC, tickets, inventory, HR, or GIS. I analyze and recommend — I never change data without your approval.',
            'cards' => [],
            'table' => null,
            'links' => [],
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

    public function refreshDashboard(): void
    {
        $this->dashboard = app(AiOperationsOrchestrator::class)->dashboard();
    }

    public function sendQuery(): void
    {
        $query = trim($this->userQuery);
        if ($query === '') {
            return;
        }

        $this->messages[] = ['role' => 'user', 'text' => $query];

        $result = app(AiOperationsOrchestrator::class)->ask($query, $this->aiSession);
        $this->aiSession = is_array($result['session'] ?? null) ? $result['session'] : [];

        $this->messages[] = [
            'role' => 'assistant',
            'text' => (string) ($result['reply'] ?? ''),
            'cards' => is_array($result['cards'] ?? null) ? $result['cards'] : [],
            'table' => $result['table'] ?? null,
            'links' => is_array($result['links'] ?? null) ? $result['links'] : [],
            'domain' => (string) ($result['domain'] ?? 'general'),
        ];

        $this->userQuery = '';
        $this->refreshDashboard();
    }

    public function askChip(string $chip): void
    {
        $this->userQuery = $chip;
        $this->sendQuery();
    }

    public function toggleAlerts(): void
    {
        $this->showAlerts = ! $this->showAlerts;
    }

    public function clearChat(): void
    {
        $this->aiSession = [];
        $this->messages = [[
            'role' => 'assistant',
            'text' => 'Conversation cleared. How can I help?',
            'cards' => [],
            'table' => null,
            'links' => [],
        ]];
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
