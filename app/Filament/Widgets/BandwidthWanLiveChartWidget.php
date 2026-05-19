<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\SafeBandwidthChartData;
use App\Services\Bandwidth\BandwidthCollectionService;
use App\Support\TenantResolver;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\On;

class BandwidthWanLiveChartWidget extends ChartWidget
{
    use SafeBandwidthChartData;

    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'MikroTik WAN port — live (Mbps/s)';

    protected static ?string $description = 'Each line = one uplink interface from router (ether1, WAN, SFP…)';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    /** @var list<string> */
    private const COLORS = ['#dc2626', '#ea580c', '#b45309', '#c2410c', '#2563eb', '#0891b2'];

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
        $minutes = (int) config('bandwidth.monitor_wan_chart_minutes', 15);
        $ifaces = BandwidthCollectionService::aggregateWanInterfacesMbpsPerSecond($tenantId, $minutes, $points);

        if ($ifaces['labels'] !== [] && $ifaces['series'] !== []) {
            $datasets = [];
            foreach ($ifaces['series'] as $i => $iface) {
                $color = self::COLORS[$i % count(self::COLORS)];
                $datasets[] = $this->dataset("{$iface['label']} ↓", $iface['download_mbps'], $color, 'rgba(220, 38, 38, 0.12)');
                $datasets[] = $this->dataset("{$iface['label']} ↑", $iface['upload_mbps'], $color, 'rgba(234, 88, 12, 0.08)', dashed: true);
            }

            return [
                'datasets' => $datasets,
                'labels' => $ifaces['labels'],
            ];
        }

        $chart = BandwidthCollectionService::aggregateWanLiveMbpsPerSecond($tenantId, $minutes, $points);

        if ($chart['labels'] === []) {
            $live = BandwidthCollectionService::currentWanLiveBps($tenantId);
            $snapshots = BandwidthCollectionService::latestWanInterfaceSnapshots($tenantId);

            if ($snapshots !== []) {
                $datasets = [];
                foreach ($snapshots as $i => $snap) {
                    $color = self::COLORS[$i % count(self::COLORS)];
                    $label = "{$snap['server']} · {$snap['interface']}";
                    $datasets[] = $this->dataset("{$label} ↓", [$snap['down_mbps']], $color, 'rgba(220, 38, 38, 0.2)');
                    $datasets[] = $this->dataset("{$label} ↑", [$snap['up_mbps']], $color, 'rgba(234, 88, 12, 0.12)', dashed: true);
                }

                return [
                    'datasets' => $datasets,
                    'labels' => [now()->format('H:i:s')],
                ];
            }

            return [
                'datasets' => [
                    $this->dataset('WAN ↓', [round($live['down_bps'] / 1_000_000, 3)], '#dc2626', 'rgba(220, 38, 38, 0.2)'),
                    $this->dataset('WAN ↑', [round($live['up_bps'] / 1_000_000, 3)], '#ea580c', 'rgba(234, 88, 12, 0.12)', dashed: true),
                ],
                'labels' => [now()->format('H:i:s')],
            ];
        }

        return [
            'datasets' => [
                $this->dataset('WAN total ↓', $chart['download_mbps'], '#dc2626', 'rgba(220, 38, 38, 0.18)'),
                $this->dataset('WAN total ↑', $chart['upload_mbps'], '#ea580c', 'rgba(234, 88, 12, 0.1)', dashed: true),
            ],
            'labels' => $chart['labels'],
        ];
    }

    /**
     * @param  list<float>  $data
     * @return array<string, mixed>
     */
    private function dataset(string $label, array $data, string $color, string $fill, bool $dashed = false): array
    {
        return [
            'label' => $label,
            'data' => $data,
            'borderColor' => $color,
            'backgroundColor' => $fill,
            'borderWidth' => $dashed ? 2 : 2.5,
            'borderDash' => $dashed ? [5, 4] : [],
            'fill' => true,
            'tension' => 0.3,
            'pointRadius' => 0,
            'pointHitRadius' => 8,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'animation' => ['duration' => 400],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => ['display' => true, 'text' => 'Mbps/s'],
                ],
                'x' => [
                    'ticks' => ['maxTicksLimit' => 12, 'maxRotation' => 0],
                ],
            ],
            'interaction' => ['mode' => 'index', 'intersect' => false],
        ];
    }
}
