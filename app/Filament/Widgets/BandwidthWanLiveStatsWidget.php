<?php

namespace App\Filament\Widgets;

use App\Services\Bandwidth\BandwidthCollectionService;
use App\Support\TenantResolver;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class BandwidthWanLiveStatsWidget extends Widget
{
    protected static string $view = 'filament.widgets.bandwidth-wan-live-stats';

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected function getPollingInterval(): ?string
    {
        $seconds = (int) config('bandwidth.monitor_wan_poll_seconds', 0);

        return $seconds > 0 ? "{$seconds}s" : null;
    }

    #[On('bandwidth-refresh')]
    public function refreshWan(): void
    {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function getWanLive(): array
    {
        try {
            $tenantId = TenantResolver::requiredTenantId();
            $bps = BandwidthCollectionService::currentWanLiveBps($tenantId);
            $ifaces = BandwidthCollectionService::latestWanInterfaceSnapshots($tenantId);

            return [
                'down_mbps' => round($bps['down_bps'] / 1_000_000, 2),
                'up_mbps' => round($bps['up_bps'] / 1_000_000, 2),
                'interfaces' => $ifaces,
                'has_data' => $bps['down_bps'] > 0 || $bps['up_bps'] > 0 || $ifaces !== [],
            ];
        } catch (\Throwable) {
            return [
                'down_mbps' => 0,
                'up_mbps' => 0,
                'interfaces' => [],
                'has_data' => false,
            ];
        }
    }
}
