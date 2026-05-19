<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\EnablesLivePolling;
use App\Services\Bandwidth\BandwidthCollectionService;
use App\Support\TenantResolver;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class BandwidthLiveCompareStatsWidget extends Widget
{
    use EnablesLivePolling;

    protected static string $view = 'filament.widgets.bandwidth-live-compare-stats';

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected function getPollingInterval(): ?string
    {
        $seconds = max(1, (int) config('bandwidth.live_chart_poll_seconds', 2));

        return "{$seconds}s";
    }

    #[On('bandwidth-refresh')]
    public function refreshCompare(): void
    {
        //
    }

    /**
     * @return array{wan_down_mbps: float, wan_up_mbps: float, users_down_mbps: float, users_up_mbps: float}
     */
    public function getLiveSnapshot(): array
    {
        $tenantId = TenantResolver::requiredTenantId();
        $wan = BandwidthCollectionService::currentWanLiveBps($tenantId);
        $users = BandwidthCollectionService::currentTenantLiveBps($tenantId);

        return [
            'wan_down_mbps' => round($wan['down_bps'] / 1_000_000, 2),
            'wan_up_mbps' => round($wan['up_bps'] / 1_000_000, 2),
            'users_down_mbps' => round($users['down_bps'] / 1_000_000, 2),
            'users_up_mbps' => round($users['up_bps'] / 1_000_000, 2),
        ];
    }
}
