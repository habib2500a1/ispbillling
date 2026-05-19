<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\SafeBandwidthChartData;
use App\Services\Bandwidth\BandwidthCollectionService;
use App\Support\TenantResolver;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\On;

class BandwidthUsersLiveChartWidget extends ChartWidget
{
    use SafeBandwidthChartData;

    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'All subscribers — live graph (Mbps per sec)';

    protected static ?string $description = 'Sum of every PPPoE user session';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getPollingInterval(): ?string
    {
        $seconds = (int) config('bandwidth.monitor_wan_poll_seconds', 0);

        return $seconds > 0 ? "{$seconds}s" : null;
    }

    #[On('bandwidth-refresh')]
    public function refreshChart(): void
    {
        //
    }

    protected function getData(): array
    {
        return $this->safeChartData(fn (): array => $this->buildChartData());
    }

    /**
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    private function buildChartData(): array
    {
        $tenantId = TenantResolver::requiredTenantId();
        $points = (int) config('bandwidth.monitor_wan_chart_points', 180);
        $chart = BandwidthCollectionService::aggregateLiveMbpsPerSecond($tenantId, 15, $points);

        if ($chart['labels'] === []) {
            $live = BandwidthCollectionService::currentTenantLiveBps($tenantId);

            return [
                'datasets' => [
                    [
                        'label' => 'Users ↓',
                        'data' => [round($live['down_bps'] / 1_000_000, 3)],
                        'borderColor' => '#2563eb',
                        'backgroundColor' => 'rgba(37, 99, 235, 0.1)',
                        'fill' => true,
                        'tension' => 0.3,
                    ],
                ],
                'labels' => [now()->format('H:i:s')],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Users ↓ download',
                    'data' => $chart['download_mbps'],
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37, 99, 235, 0.12)',
                    'fill' => true,
                    'tension' => 0.3,
                    'pointRadius' => 0,
                ],
                [
                    'label' => 'Users ↑ upload',
                    'data' => $chart['upload_mbps'],
                    'borderColor' => '#0891b2',
                    'backgroundColor' => 'rgba(8, 145, 178, 0.08)',
                    'fill' => true,
                    'tension' => 0.3,
                    'pointRadius' => 0,
                    'borderDash' => [4, 3],
                ],
            ],
            'labels' => $chart['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'Mbps/s']],
            ],
        ];
    }
}
