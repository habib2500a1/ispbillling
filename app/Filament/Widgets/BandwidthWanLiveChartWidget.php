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

    protected static string $view = 'filament.widgets.bandwidth-wan-live-chart';

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '320px';

    public float $liveDownMbps = 0;

    public float $liveUpMbps = 0;

    public int $activeIfaceCount = 0;

    /** @var list<string> */
    private const DOWN_COLORS = ['#0ea5e9', '#2563eb', '#7c3aed', '#0891b2'];

    /** @var list<string> */
    private const UP_COLORS = ['#f97316', '#ea580c', '#db2777', '#ca8a04'];

    protected function getPollingInterval(): ?string
    {
        $seconds = max(1, (int) config('bandwidth.monitor_wan_chart_poll_seconds', 1));

        return "{$seconds}s";
    }

    public function mount(): void
    {
        parent::mount();
        $this->syncHeaderStats();
    }

    public function pollWanChart(): void
    {
        $this->syncHeaderStats();
        $this->updateChartData();
    }

    #[On('bandwidth-refresh')]
    public function refreshChart(): void
    {
        $this->pollWanChart();
    }

    /**
     * @return array{down_mbps: float, up_mbps: float, iface_count: int, poll_seconds: int, collect_seconds: int}
     */
    public function getChartHeader(): array
    {
        return [
            'down_mbps' => $this->liveDownMbps,
            'up_mbps' => $this->liveUpMbps,
            'iface_count' => $this->activeIfaceCount,
            'poll_seconds' => max(1, (int) config('bandwidth.monitor_wan_chart_poll_seconds', 1)),
            'collect_seconds' => max(3, (int) config('bandwidth.monitor_wan_collect_seconds', 3)),
        ];
    }

    private function syncHeaderStats(): void
    {
        try {
            $tenantId = TenantResolver::requiredTenantId();
            $chart = BandwidthCollectionService::aggregateWanLiveChartSeries($tenantId);
            $this->liveDownMbps = $chart['down_mbps'];
            $this->liveUpMbps = $chart['up_mbps'];
            $this->activeIfaceCount = count($chart['series']);
        } catch (\Throwable) {
            $this->liveDownMbps = 0;
            $this->liveUpMbps = 0;
            $this->activeIfaceCount = 0;
        }
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
        $points = (int) config('bandwidth.monitor_wan_chart_points', 120);
        $chart = BandwidthCollectionService::aggregateWanLiveChartSeries($tenantId, $points);

        if ($chart['labels'] === [] || $chart['series'] === []) {
            return [
                'datasets' => [
                    $this->dataset('WAN ↓', [0], self::DOWN_COLORS[0], 'rgba(14, 165, 233, 0.15)'),
                    $this->dataset('WAN ↑', [0], self::UP_COLORS[0], 'rgba(249, 115, 22, 0.08)', dashed: true),
                ],
                'labels' => [now()->format('H:i:s')],
            ];
        }

        $datasets = [];
        foreach ($chart['series'] as $i => $iface) {
            $downColor = self::DOWN_COLORS[$i % count(self::DOWN_COLORS)];
            $upColor = self::UP_COLORS[$i % count(self::UP_COLORS)];
            $label = $iface['label'];
            $datasets[] = $this->dataset("{$label} ↓", $iface['download_mbps'], $downColor, $this->chartFill($downColor, 0.14));
            $datasets[] = $this->dataset("{$label} ↑", $iface['upload_mbps'], $upColor, $this->chartFill($upColor, 0.07), dashed: true);
        }

        return [
            'datasets' => $datasets,
            'labels' => $chart['labels'],
        ];
    }

    /**
     * @param  list<float|int>  $data
     * @return array<string, mixed>
     */
    private function dataset(string $label, array $data, string $color, string $fill, bool $dashed = false): array
    {
        return [
            'label' => $label,
            'data' => $data,
            'borderColor' => $color,
            'backgroundColor' => $fill,
            'borderWidth' => $dashed ? 1.75 : 2.25,
            'borderDash' => $dashed ? [6, 4] : [],
            'fill' => true,
            'tension' => 0.35,
            'pointRadius' => 0,
            'pointHitRadius' => 10,
        ];
    }

    private function chartFill(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return 'rgba(14, 165, 233, 0.12)';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'animation' => ['duration' => 280],
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'boxWidth' => 10,
                        'boxHeight' => 10,
                        'usePointStyle' => true,
                        'padding' => 16,
                        'font' => ['size' => 11, 'weight' => '600'],
                    ],
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                    'backgroundColor' => 'rgba(15, 23, 42, 0.92)',
                    'padding' => 12,
                    'titleFont' => ['size' => 12, 'weight' => '700'],
                    'bodyFont' => ['size' => 11],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'grid' => ['color' => 'rgba(148, 163, 184, 0.18)'],
                    'ticks' => ['maxTicksLimit' => 6, 'font' => ['size' => 10]],
                    'title' => ['display' => true, 'text' => 'Mbps/s', 'font' => ['size' => 11, 'weight' => '600']],
                ],
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => ['maxTicksLimit' => 8, 'maxRotation' => 0, 'font' => ['size' => 10]],
                ],
            ],
            'interaction' => ['mode' => 'index', 'intersect' => false],
        ];
    }
}
