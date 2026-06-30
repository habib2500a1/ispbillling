<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Models\AiActionRequest;
use App\Services\Ai\AiActionApprovalService;
use App\Support\TenantResolver;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class AiActionApprovalHub extends Page
{
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static string $view = 'filament.pages.ai-action-approval-hub';

    protected static ?string $navigationLabel = 'AI action queue';

    protected static ?string $title = 'AI action approvals';

    protected static ?string $slug = 'ai-action-approvals';

    protected static bool $shouldRegisterNavigation = false;

    /** @var \Illuminate\Support\Collection<int, AiActionRequest> */
    public $pendingActions;

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->loadPending();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super-admin', 'isp-admin', 'isp-manager']) ?? false;
    }

    public function loadPending(): void
    {
        $this->pendingActions = app(AiActionApprovalService::class)->pendingForTenant(TenantResolver::requiredTenantId());
    }

    public function approveAction(int $actionId): void
    {
        abort_unless(static::canAccess(), 403);

        try {
            app(AiActionApprovalService::class)->approve($actionId, auth()->user());
            Notification::make()->title('Action approved and executed')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Approval failed')->body($e->getMessage())->danger()->send();
        }

        $this->loadPending();
    }

    public function rejectAction(int $actionId): void
    {
        abort_unless(static::canAccess(), 403);

        try {
            app(AiActionApprovalService::class)->reject($actionId, auth()->user());
            Notification::make()->title('Action rejected')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Rejection failed')->body($e->getMessage())->danger()->send();
        }

        $this->loadPending();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->loadPending()),
        ];
    }
}
