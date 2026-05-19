<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BandwidthAbuseAlertsWidget;
use App\Filament\Widgets\BandwidthDailyUsageWidget;
use App\Filament\Widgets\BandwidthMonitorStatsWidget;
use App\Filament\Widgets\BandwidthUsersLiveChartWidget;
use App\Filament\Widgets\BandwidthWanLiveChartWidget;
use App\Filament\Widgets\BandwidthWanLiveStatsWidget;
use App\Filament\Widgets\BandwidthOnlineSessionsWidget;
use App\Filament\Widgets\BandwidthSessionHistoryWidget;
use App\Services\Bandwidth\BandwidthCollectionService;
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
     * Lightweight poll: refresh charts from DB only (no MikroTik API — avoids gateway timeout).
     */
    public function refreshLiveData(): void
    {
        $this->dispatch('bandwidth-refresh');
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
                    try {
                        $tenantId = TenantResolver::requiredTenantId();
                        $result = app(BandwidthCollectionService::class)->collectForTenant($tenantId);
                        $this->dispatch('bandwidth-refresh');
                        $lines = [
                            sprintf(
                                'Router: %d session(s) · WAN samples: %d · Matched: %d · Online: %d',
                                $result['api_sessions'],
                                $result['wan_samples'] ?? 0,
                                $result['matched_subscribers'],
                                $result['sessions_open'],
                            ),
                        ];

                        if ($result['api_errors'] !== []) {
                            $lines[] = 'API: '.implode(' | ', array_slice($result['api_errors'], 0, 2));
                        }

                        if ($result['unmatched_logins'] !== []) {
                            $sample = implode(', ', array_slice($result['unmatched_logins'], 0, 5));
                            $more = count($result['unmatched_logins']) > 5
                                ? ' (+'.(count($result['unmatched_logins']) - 5).' more)'
                                : '';
                            $lines[] = 'Unmatched PPP logins: '.$sample.$more;
                        }

                        if (! $result['api_ok'] && $result['api_sessions'] === 0) {
                            Notification::make()
                                ->title('Sync incomplete — MikroTik not reachable')
                                ->body(implode("\n", $lines))
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('PPP online sync complete')
                            ->body(implode("\n", $lines))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Sync failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }
}
