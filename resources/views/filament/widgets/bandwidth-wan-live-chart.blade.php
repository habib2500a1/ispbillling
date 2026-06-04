@php
    use Filament\Support\Facades\FilamentView;

    $header = $this->getChartHeader();
    $color = $this->getColor();
    $cssV = @filemtime(public_path('css/wan-live-chart-pro.css')) ?: time();
@endphp

<x-filament-widgets::widget class="fi-wi-chart isp-wan-chart-widget">
    <link rel="stylesheet" href="{{ asset('css/wan-live-chart-pro.css') }}?v={{ $cssV }}">

    <div class="isp-wan-chart">
        <header class="isp-wan-chart__head">
            <div class="isp-wan-chart__title-block">
                <div class="isp-wan-chart__title-row">
                    <x-filament::icon icon="heroicon-m-signal" class="isp-wan-chart__icon" />
                    <h3 class="isp-wan-chart__title">MikroTik WAN port — live</h3>
                    <span class="isp-wan-chart__badge">{{ $header['poll_seconds'] }}s</span>
                </div>
                <p class="isp-wan-chart__sub">
                    Real-time uplink · router sample every {{ $header['collect_seconds'] }}s · chart refresh {{ $header['poll_seconds'] }}s
                    @if ($header['iface_count'] > 0)
                        · {{ $header['iface_count'] }} active port{{ $header['iface_count'] === 1 ? '' : 's' }}
                    @endif
                </p>
            </div>
            <div class="isp-wan-chart__pills" aria-live="polite">
                <span class="isp-wan-chart__pill isp-wan-chart__pill--down">
                    <span class="isp-wan-chart__pill-value">{{ number_format($header['down_mbps'], 2) }}</span>
                    <span class="isp-wan-chart__pill-arrow" aria-hidden="true">↓</span>
                    <span class="isp-wan-chart__pill-unit">Mbps</span>
                </span>
                <span class="isp-wan-chart__pill isp-wan-chart__pill--up">
                    <span class="isp-wan-chart__pill-value">{{ number_format($header['up_mbps'], 2) }}</span>
                    <span class="isp-wan-chart__pill-arrow" aria-hidden="true">↑</span>
                    <span class="isp-wan-chart__pill-unit">Mbps</span>
                </span>
            </div>
        </header>

        <div
            class="isp-wan-chart__canvas-wrap"
            @if ($pollingInterval = $this->getPollingInterval())
                wire:poll.{{ $pollingInterval }}="pollWanChart"
            @endif
        >
            <div
                @if (FilamentView::hasSpaMode())
                    x-load="visible"
                @else
                    x-load
                @endif
                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                wire:ignore
                x-data="chart({
                    cachedData: @js($this->getCachedData()),
                    options: @js($this->getOptions()),
                    type: @js($this->getType()),
                })"
                @class([
                    'isp-wan-chart__canvas',
                    match ($color) {
                        'gray' => null,
                        default => 'fi-color-custom',
                    },
                    is_string($color) ? "fi-color-{$color}" : null,
                ])
            >
                <canvas x-ref="canvas" style="max-height: {{ $this->getMaxHeight() ?? '320px' }}"></canvas>

                <span x-ref="backgroundColorElement" class="text-gray-100 dark:text-gray-800"></span>
                <span x-ref="borderColorElement" class="text-gray-400"></span>
                <span x-ref="gridColorElement" class="text-gray-200 dark:text-gray-800"></span>
                <span x-ref="textColorElement" class="text-gray-500 dark:text-gray-400"></span>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
