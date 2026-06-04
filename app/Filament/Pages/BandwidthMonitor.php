<?php

namespace App\Filament\Pages;

use App\Support\Rbac\StaffCapability;

use App\Filament\Widgets\BandwidthAbuseAlertsWidget;
use App\Filament\Widgets\BandwidthDailyUsageWidget;
use App\Filament\Widgets\BandwidthMonitorStatsWidget;
use App\Filament\Widgets\BandwidthUsersLiveChartWidget;
use App\Filament\Widgets\BandwidthWanLiveChartWidget;
use App\Filament\Widgets\BandwidthWanLiveStatsWidget;
use App\Filament\Widgets\BandwidthOnlineSessionsWidget;
use App\Filament\Widgets\BandwidthSessionHistoryWidget;
use App\Services\Bandwidth\BandwidthSyncDispatcher;
use App\Services\Bandwidth\BandwidthSyncStatus;
use App\Support\TenantResolver;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\On;

class BandwidthMonitor extends Page
{
    protected static ?string $slug = 'bandwidth-monitor';

    /**
     * Poll WAN graphs tab: fetch interface counters from MikroTik (throttled).
     */
    public function refreshLiveData(): void
    {
        if ($this->activeTab === 'graphs') {
            app(BandwidthSyncDispatcher::class)->queueRefreshWanLiveSamples(
                TenantResolver::requiredTenantId(),
            );
        }

        $this->dispatch('bandwidth-refresh');
    }

    private function bootstrapWanLive(): void
    {
        app(BandwidthSyncDispatcher::class)->queueRefreshWanLiveSamples(
            TenantResolver::requiredTenantId(),
            force: true,
        );
    }

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.pages.bandwidth-monitor';

    protected static ?string $navigationLabel = 'Bandwidth monitor';

    protected static ?string $title = 'Bandwidth & usage';

    protected static ?string $navigationGroup = 'Network';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 5;

    public string $activeTab = 'online';

    protected function getHeaderWidgets(): array
    {
        return match ($this->activeTab) {
            'graphs' => [
                BandwidthWanLiveStatsWidget::class,
                BandwidthWanLiveChartWidget::class,
                BandwidthUsersLiveChartWidget::class,
            ],
            default => [
                BandwidthMonitorStatsWidget::class,
            ],
        };
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 1;
    }

    protected function getFooterWidgets(): array
    {
        return match ($this->activeTab) {
            'history' => [BandwidthSessionHistoryWidget::class],
            'usage' => [BandwidthDailyUsageWidget::class],
            'abuse' => [BandwidthAbuseAlertsWidget::class],
            'graphs' => [],
            default => [BandwidthOnlineSessionsWidget::class],
        };
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, ['online', 'history', 'usage', 'abuse', 'graphs'], true)) {
            return;
        }

        $this->activeTab = $tab;

        if ($tab === 'graphs') {
            $this->bootstrapWanLive();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getSyncStatus(): array
    {
        try {
            return BandwidthSyncStatus::get(TenantResolver::requiredTenantId());
        } catch (\Throwable) {
            return [
                'api' => ['ok' => false, 'sessions' => 0, 'error' => 'Status unavailable'],
                'radius' => ['ok' => false, 'sessions' => 0],
                'merged_active' => 0,
                'unmatched_logins' => [],
                'updated_at' => null,
            ];
        }
    }

    #[On('bandwidth-refresh')]
    public function refreshBandwidthData(): void
    {
        // Re-render header/footer widgets when refresh is dispatched.
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Sync now')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    $tenantId = TenantResolver::requiredTenantId();
                    $dispatcher = app(BandwidthSyncDispatcher::class);

                    if (BandwidthSyncStatus::isRunning($tenantId)) {
                        Notification::make()
                            ->title('Sync already running')
                            ->body('Background sync is in progress — this page will refresh automatically.')
                            ->info()
                            ->send();

                        return;
                    }

                    if (! $dispatcher->queueCollectForTenant($tenantId)) {
                        Notification::make()
                            ->title('Sync unavailable')
                            ->body('Bandwidth collection is disabled.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $this->dispatch('bandwidth-refresh');

                    Notification::make()
                        ->title('Sync started')
                        ->body('Running in background — counts and graphs update without blocking the page.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public static function canAccess(): bool
    {
        return \App\Support\Rbac\StaffCapability::for(auth()->user())->canMikrotik();
    }
}
