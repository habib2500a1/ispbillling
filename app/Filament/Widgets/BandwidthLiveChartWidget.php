<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\EnablesLivePolling;
use App\Services\Bandwidth\BandwidthCollectionService;
use App\Support\TenantResolver;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\On;

class BandwidthLiveChartWidget extends ChartWidget
{
    protected static bool $isDiscovered = false;

    use EnablesLivePolling;

    protected static ?string $heading = 'MikroTik WAN port vs subscribers';

    protected static ?string $description = 'Solid = MikroTik uplink port (ether1, WAN) · Dashed = all PPPoE users · Mbps/s';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    /** @var list<string> */
    private const WAN_COLORS = ['#dc2626', '#ea580c', '#b45309', '#c2410c', '#9a3412'];

    protected function getPollingInterval(): ?string
    {
        $seconds = max(1, (int) config('bandwidth.live_chart_poll_seconds', 2));

        return "{$seconds}s";
    }

    #[On('bandwidth-refresh')]
    public function refreshChart(): void
    {
        //
    }

    protected function getData(): array
    {
        $tenantId = TenantResolver::requiredTenantId();
        $points = (int) config('bandwidth.live_chart_points', 120);
        $chart = BandwidthCollectionService::aggregateLiveComparisonChart($tenantId, 30, $points);

        if ($chart['labels'] === []) {
            $chart = [
                'labels' => [now()->format('H:i:s')],
                'wan_series' => [],
                'wan_download_mbps' => [0],
                'wan_upload_mbps' => [0],
                'users_download_mbps' => [0],
                'users_upload_mbps' => [0],
            ];
        }

        $datasets = [];
        $wanSeries = $chart['wan_series'] ?? [];

        if ($wanSeries !== []) {
            foreach ($wanSeries as $i => $iface) {
                $color = self::WAN_COLORS[$i % count(self::WAN_COLORS)];
                $datasets[] = [
                    'label' => 'MT '.$iface['label'].' ↓',
                    'data' => $iface['download_mbps'],
                    'borderColor' => $color,
                    'backgroundColor' => 'transparent',
                    'borderWidth' => 2.5,
                    'fill' => false,
                    'tension' => 0.25,
                ];
            }
        } else {
            $datasets[] = [
                'label' => 'MikroTik WAN ↓ (total)',
                'data' => $chart['wan_download_mbps'],
                'borderColor' => '#dc2626',
                'backgroundColor' => 'rgba(220, 38, 38, 0.08)',
                'borderWidth' => 2.5,
                'fill' => false,
                'tension' => 0.25,
            ];
            $datasets[] = [
                'label' => 'MikroTik WAN ↑ (total)',
                'data' => $chart['wan_upload_mbps'],
                'borderColor' => '#f97316',
                'backgroundColor' => 'rgba(249, 115, 22, 0.06)',
                'borderWidth' => 2,
                'borderDash' => [6, 4],
                'fill' => false,
                'tension' => 0.25,
            ];
        }

        $datasets[] = [
            'label' => 'PPPoE users ↓',
            'data' => $chart['users_download_mbps'],
            'borderColor' => '#2563eb',
            'backgroundColor' => 'rgba(37, 99, 235, 0.06)',
            'borderWidth' => 2,
            'borderDash' => [4, 3],
            'fill' => false,
            'tension' => 0.25,
        ];
        $datasets[] = [
            'label' => 'PPPoE users ↑',
            'data' => $chart['users_upload_mbps'],
            'borderColor' => '#0891b2',
            'backgroundColor' => 'rgba(8, 145, 178, 0.06)',
            'borderWidth' => 2,
            'borderDash' => [2, 2],
            'fill' => false,
            'tension' => 0.25,
        ];

        return [
            'datasets' => $datasets,
            'labels' => $chart['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
