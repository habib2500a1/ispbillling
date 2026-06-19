@php
    $series = $this->trafficSeries;
    $labels = $series['labels'] ?? [];
    $download = $series['download_mbps'] ?? [];
    $upload = $series['upload_mbps'] ?? [];
@endphp

{!! \App\Support\OltStyles::navigatedScript() !!}

<x-filament-panels::page class="isp-olt-traffic-page" wire:poll.30s>
    <div class="olt-pro space-y-5">
        <header class="olt-hero">
            <div class="olt-hero__grid">
                <span class="olt-hero__badge">
                    <span class="olt-hero__badge-dot" aria-hidden="true"></span>
                    IF-MIB uplink traffic
                </span>
                <h1 class="olt-hero__title">OLT live traffic</h1>
                <p class="olt-hero__sub">
                    SNMP HC octet counters — download/upload Mbps per OLT chassis. Poll now forces a fresh SNMP sample.
                </p>
            </div>
            <div class="olt-hero__live">
                <div class="olt-hero__live-card">
                    <span class="olt-hero__live-label">Download now</span>
                    <strong class="olt-hero__live-value">
                        {{ $series['current_download_mbps'] !== null ? number_format((float) $series['current_download_mbps'], 1).' Mbps' : '—' }}
                    </strong>
                    <span class="olt-hero__live-hint">
                        Upload {{ $series['current_upload_mbps'] !== null ? number_format((float) $series['current_upload_mbps'], 1).' Mbps' : '—' }}
                    </span>
                </div>
            </div>
        </header>

        <section class="olt-oc-panel">
            <div class="olt-oc-panel__head flex flex-wrap items-center justify-between gap-3">
                <span>Traffic history</span>
                <div class="flex flex-wrap items-center gap-2">
                    <select wire:model.live="filterOlt" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                        @foreach ($this->oltOptions as $olt)
                            <option value="{{ $olt['id'] }}">{{ $olt['label'] }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="period" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                        @foreach (['1h' => '1 hour', '24h' => '24 hours', '7d' => '7 days', '30d' => '30 days'] as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="pollNow" class="olt-btn olt-btn--white text-sm">
                        Poll now
                    </button>
                </div>
            </div>
            <div class="p-4">
                <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <p class="text-xs uppercase text-gray-500">Peak download</p>
                        <p class="text-xl font-bold tabular-nums">{{ number_format((float) ($series['peak_download_mbps'] ?? 0), 1) }} Mbps</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <p class="text-xs uppercase text-gray-500">Peak upload</p>
                        <p class="text-xl font-bold tabular-nums">{{ number_format((float) ($series['peak_upload_mbps'] ?? 0), 1) }} Mbps</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <p class="text-xs uppercase text-gray-500">Current download</p>
                        <p class="text-xl font-bold tabular-nums">{{ $series['current_download_mbps'] !== null ? number_format((float) $series['current_download_mbps'], 1).' Mbps' : '—' }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <p class="text-xs uppercase text-gray-500">Current upload</p>
                        <p class="text-xl font-bold tabular-nums">{{ $series['current_upload_mbps'] !== null ? number_format((float) $series['current_upload_mbps'], 1).' Mbps' : '—' }}</p>
                    </div>
                </div>
                <canvas id="olt-traffic-chart" height="220" wire:ignore></canvas>
            </div>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            const labels = @json($labels);
            const download = @json($download);
            const upload = @json($upload);

            function renderChart() {
                const el = document.getElementById('olt-traffic-chart');
                if (!el || typeof Chart === 'undefined') return;
                if (el._chart) el._chart.destroy();
                el._chart = new Chart(el, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: 'Download Mbps',
                                data: download,
                                borderColor: '#06b6d4',
                                backgroundColor: 'rgba(6,182,212,0.12)',
                                fill: true,
                                tension: 0.25,
                            },
                            {
                                label: 'Upload Mbps',
                                data: upload,
                                borderColor: '#8b5cf6',
                                backgroundColor: 'rgba(139,92,246,0.08)',
                                fill: true,
                                tension: 0.25,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        interaction: { mode: 'index', intersect: false },
                        scales: { y: { beginAtZero: true, title: { display: true, text: 'Mbps' } } },
                    },
                });
            }

            renderChart();
            document.addEventListener('livewire:navigated', renderChart);
            Livewire.hook('morph.updated', ({ el }) => {
                if (el?.querySelector?.('#olt-traffic-chart')) renderChart();
            });
        })();
    </script>
</x-filament-panels::page>
