<?php

namespace App\Filament\Resources\CustomerResource\Widgets;

use App\Models\Customer;
use App\Services\Bandwidth\SubscriberLiveTrafficService;
use App\Support\BandwidthDirection;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class SubscriberLiveTrafficWidget extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = true;

    public ?Model $record = null;

    protected static ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = 'full';

    /**
     * @var array{
     *     chart: array{labels: list<string>, download_mbps: list<float>, upload_mbps: list<float>},
     *     rx_bps: ?int,
     *     tx_bps: ?int
     * }|null
     */
    private ?array $liveSnapshot = null;

    protected function getPollingInterval(): ?string
    {
        $seconds = (int) config('bandwidth.subscriber_chart_poll_seconds', 1);

        return $seconds > 0 ? "{$seconds}s" : null;
    }

    public function getHeading(): string|Htmlable|null
    {
        if (! $this->record instanceof Customer) {
            return 'Live traffic';
        }

        $login = $this->record->pppLoginName() ?: $this->record->customer_code;

        return "Traffic monitor: {$login}";
    }

    public function getDescription(): string|Htmlable|null
    {
        if (! $this->record instanceof Customer) {
            return 'Select a subscriber to view live TX/RX.';
        }

        $snapshot = $this->snapshot();
        $tx = BandwidthDirection::formatBps($snapshot['tx_bps']);
        $rx = BandwidthDirection::formatBps($snapshot['rx_bps']);

        $poll = (int) config('bandwidth.subscriber_chart_poll_seconds', 1);
        $hint = " · Updates every {$poll}s";

        if (! $this->record->isPppOnline()) {
            $hint .= ' · Offline';
        }

        return "TX (upload) {$tx} · RX (download) {$rx}{$hint}";
    }

    protected function getData(): array
    {
        if (! $this->record instanceof Customer) {
            return $this->emptyChart();
        }

        $snapshot = $this->snapshot();
        $chart = $snapshot['chart'];

        if ($chart['labels'] === []) {
            $rxMbps = ($snapshot['rx_bps'] ?? 0) / 1_000_000;
            $txMbps = ($snapshot['tx_bps'] ?? 0) / 1_000_000;

            return [
                'datasets' => [
                    $this->dataset('RX (download)', [$rxMbps], '#ef4444', 'rgba(239, 68, 68, 0.12)', 4),
                    $this->dataset('TX (upload)', [$txMbps], '#3b82f6', 'rgba(59, 130, 246, 0.12)', 4),
                ],
                'labels' => [now()->format('H:i:s')],
            ];
        }

        return [
            'datasets' => [
                $this->dataset('RX (download)', $chart['download_mbps'], '#ef4444', 'rgba(239, 68, 68, 0.08)'),
                $this->dataset('TX (upload)', $chart['upload_mbps'], '#3b82f6', 'rgba(59, 130, 246, 0.08)'),
            ],
            'labels' => $chart['labels'],
        ];
    }

    /**
     * @return array{
     *     chart: array{labels: list<string>, download_mbps: list<float>, upload_mbps: list<float>},
     *     rx_bps: ?int,
     *     tx_bps: ?int
     * }
     */
    private function snapshot(): array
    {
        if ($this->liveSnapshot !== null) {
            return $this->liveSnapshot;
        }

        if (! $this->record instanceof Customer) {
            return $this->liveSnapshot = [
                'chart' => ['labels' => [], 'download_mbps' => [], 'upload_mbps' => []],
                'rx_bps' => null,
                'tx_bps' => null,
            ];
        }

        $service = app(SubscriberLiveTrafficService::class);
        $this->liveSnapshot = $service->tick($this->record);
        $service->maybePersistSessionRates(
            $this->record,
            $this->liveSnapshot['rx_bps'],
            $this->liveSnapshot['tx_bps'],
        );

        return $this->liveSnapshot;
    }

    /**
     * @param  list<float>  $data
     * @return array<string, mixed>
     */
    private function dataset(string $label, array $data, string $border, string $fill, ?int $pointRadius = null): array
    {
        $set = [
            'label' => $label,
            'data' => $data,
            'borderColor' => $border,
            'backgroundColor' => $fill,
            'fill' => true,
            'tension' => 0.25,
        ];

        if ($pointRadius !== null) {
            $set['pointRadius'] = $pointRadius;
        }

        return $set;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyChart(): array
    {
        return [
            'datasets' => [
                $this->dataset('RX (download)', [0], '#ef4444', 'rgba(239, 68, 68, 0.08)'),
                $this->dataset('TX (upload)', [0], '#3b82f6', 'rgba(59, 130, 246, 0.08)'),
            ],
            'labels' => [now()->format('H:i:s')],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'animation' => false,
            'scales' => [
                'x' => [
                    'ticks' => [
                        'maxTicksLimit' => 12,
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Mbps',
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
        ];
    }
}
