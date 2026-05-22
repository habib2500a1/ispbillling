@php
    $primary = $ops['primary'] ?? [];
    $sections = $ops['sections'] ?? [];
    $feeds = $ops['feeds'] ?? [];
    $chart = $ops['revenue_chart'] ?? ['labels' => [], 'collected' => [], 'invoiced' => []];
    $highlights = $ops['highlights'] ?? [];
    $mfsPending = $ops['mfs_pending_verify'] ?? ['count' => 0, 'url' => null, 'items' => []];
    $updated = $ops['updated_at'] ?? null;
@endphp

<x-filament-widgets::widget>
    <div class="isp-cmd-center isp-cmd-center--pro">
        <header class="isp-cmd-hero isp-cmd-hero--pro">
            <div class="isp-cmd-hero__main">
                <div class="isp-cmd-hero__live">
                    <span class="isp-live-dot" aria-hidden="true"></span>
                    Operations command center
                    <span class="isp-cmd-hero__sep">·</span>
                    {{ $ops['company'] ?? config('app.name') }}
                </div>
                <h2 class="isp-cmd-hero__title">Welcome, {{ auth()->user()?->name }}</h2>
                <p class="isp-cmd-hero__lead">
                    {{ now()->format('l, d F Y') }} — subscribers, billing, network ও support এক জায়গায়।
                </p>
            </div>
            <div class="isp-cmd-hero__chips">
                @foreach ($highlights as $chip)
                    @if (! empty($chip['url']))
                        <a href="{{ $chip['url'] }}" class="isp-cmd-chip">{{ $chip['label'] }}: <strong>{{ $chip['value'] }}</strong></a>
                    @else
                        <span class="isp-cmd-chip">{{ $chip['label'] }}: <strong>{{ $chip['value'] }}</strong></span>
                    @endif
                @endforeach
                @if ($updated)
                    <span class="isp-cmd-chip isp-cmd-chip--muted">{{ \Carbon\Carbon::parse($updated)->diffForHumans() }}</span>
                @endif
            </div>
            <div class="isp-cmd-hero__actions">
                <a href="{{ \App\Filament\Resources\CustomerResource::getUrl('create') }}" class="isp-quick-pill isp-quick-pill-primary">+ New subscriber</a>
                <a href="{{ \App\Filament\Pages\BillCollectionDesk::getUrl() }}" class="isp-quick-pill">Collection desk</a>
                <a href="{{ \App\Filament\Pages\OnlineClientsMonitoring::getUrl() }}" class="isp-quick-pill">Online users</a>
            </div>
        </header>

        @if (($mfsPending['count'] ?? 0) > 0)
            <div class="isp-mfs-pending-alert" role="alert">
                <div class="isp-mfs-pending-alert__main">
                    <strong>{{ $mfsPending['count'] }} টি MFS পেমেন্ট যাচাই বাকি</strong>
                    <p>ক্লায়েন্ট ভুল TrxID দিয়েছে বা SMS মিলেনি — Pending gateway payments থেকে Approve করুন।</p>
                </div>
                @if (! empty($mfsPending['url']))
                    <a href="{{ $mfsPending['url'] }}" class="isp-mfs-pending-alert__cta">Verify &amp; approve →</a>
                @endif
            </div>
        @endif

        <div class="isp-cmd-primary">
            @foreach ($primary as $kpi)
                <a
                    href="{{ $kpi['url'] ?? '#' }}"
                    @class([
                        'isp-cmd-primary__card',
                        'isp-cmd-primary__card--' . ($kpi['tone'] ?? 'teal'),
                        'isp-cmd-primary__card--static' => empty($kpi['url']),
                    ])
                >
                    <span class="isp-cmd-primary__label">{{ $kpi['label'] }}</span>
                    <strong class="isp-cmd-primary__value">{{ $kpi['value'] }}</strong>
                    <span class="isp-cmd-primary__hint">{{ $kpi['hint'] }}</span>
                </a>
            @endforeach
        </div>

        <div class="isp-cmd-sections">
            @foreach ($sections as $section)
                <section @class(['isp-cmd-section', 'isp-cmd-section--' . ($section['accent'] ?? 'teal')])>
                    <header class="isp-cmd-section__title">
                        <span class="isp-cmd-section__icon">
                            <x-filament::icon :icon="$section['icon']" class="h-4 w-4" />
                        </span>
                        <span class="isp-cmd-section__name">{{ $section['title'] }}</span>
                        <span class="isp-cmd-section__count">{{ count($section['cards'] ?? []) }}</span>
                    </header>
                    <div class="isp-cmd-section__grid">
                        @foreach ($section['cards'] as $card)
                            <a
                                href="{{ $card['url'] ?? '#' }}"
                                @class([
                                    'isp-cmd-metric',
                                    'isp-cmd-metric--' . ($card['tone'] ?? 'slate'),
                                    'isp-cmd-metric--static' => empty($card['url']),
                                ])
                            >
                                <span class="isp-cmd-metric__value">{{ $card['value'] }}</span>
                                <span class="isp-cmd-metric__label">{{ $card['label'] }}</span>
                                @if (! empty($card['url']))
                                    <x-heroicon-m-chevron-right class="isp-cmd-metric__arrow" />
                                @endif
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <div class="isp-cmd-feeds">
            @if (($mfsPending['count'] ?? 0) > 0)
                @include('filament.widgets.partials.ops-feed-table', [
                    'title' => 'MFS pending verify (wrong TrxID / SMS)',
                    'columns' => ['Gateway', 'TrxID', 'BDT', 'Subscriber', 'When'],
                    'rows' => collect($mfsPending['items'] ?? [])->map(fn ($r) => [
                        ['text' => $r['gateway']],
                        ['text' => $r['trx'], 'url' => $r['url'] ?? null],
                        ['text' => $r['amount']],
                        ['text' => $r['customer']],
                        ['text' => $r['at']],
                    ]),
                ])
            @endif
            @include('filament.widgets.partials.ops-feed-table', [
                'title' => 'Recent invoices',
                'columns' => ['Invoice', 'Subscriber', 'BDT'],
                'rows' => collect($feeds['invoices'] ?? [])->map(fn ($r) => [
                    ['text' => $r['no'], 'url' => $r['url']],
                    ['text' => $r['user'], 'url' => $r['url']],
                    ['text' => $r['amount']],
                ]),
            ])
            @include('filament.widgets.partials.ops-feed-table', [
                'title' => 'Expiring soon (7 days)',
                'columns' => ['Subscriber', 'Package BDT', 'Expires'],
                'rows' => collect($feeds['upcoming_expire'] ?? [])->map(fn ($r) => [
                    ['text' => $r['user'], 'url' => $r['url']],
                    ['text' => $r['bill']],
                    ['text' => $r['expire']],
                ]),
            ])
            @include('filament.widgets.partials.ops-feed-table', [
                'title' => 'Recently expired',
                'columns' => ['Subscriber', 'Package BDT', 'Expired'],
                'rows' => collect($feeds['latest_expired'] ?? [])->map(fn ($r) => [
                    ['text' => $r['user'], 'url' => $r['url']],
                    ['text' => $r['bill']],
                    ['text' => $r['expire']],
                ]),
            ])
            @include('filament.widgets.partials.ops-feed-table', [
                'title' => 'Top due balance',
                'columns' => ['Subscriber', 'Due BDT'],
                'rows' => collect($feeds['top_due'] ?? [])->map(fn ($r) => [
                    ['text' => $r['user'], 'url' => $r['url']],
                    ['text' => $r['due']],
                ]),
            ])
        </div>

        <section class="isp-cmd-chart">
            <header class="isp-cmd-chart__head">
                <h3>Revenue trend</h3>
                <span>Collection vs invoice · 14 days</span>
            </header>
            <div class="isp-cmd-chart__canvas">
                <canvas
                    id="isp-cmd-revenue-chart"
                    wire:ignore
                    data-labels="{{ json_encode($chart['labels'] ?? []) }}"
                    data-collected="{{ json_encode($chart['collected'] ?? []) }}"
                    data-invoiced="{{ json_encode($chart['invoiced'] ?? []) }}"
                ></canvas>
            </div>
            <div class="isp-cmd-chart__legend">
                <span><i class="isp-cmd-legend-dot isp-cmd-legend-dot--collected"></i> Collected</span>
                <span><i class="isp-cmd-legend-dot isp-cmd-legend-dot--invoiced"></i> Invoiced</span>
            </div>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
    <script>
        (function () {
            function themeColors() {
                var dark = document.documentElement.classList.contains('dark');
                return {
                    grid: dark ? 'rgba(148,163,184,0.12)' : 'rgba(148,163,184,0.2)',
                    text: dark ? '#94a3b8' : '#64748b',
                };
            }
            function paintOpsChart() {
                var el = document.getElementById('isp-cmd-revenue-chart');
                if (!el || typeof Chart === 'undefined') return;
                var c = themeColors();
                var labels = JSON.parse(el.dataset.labels || '[]');
                var collected = JSON.parse(el.dataset.collected || '[]');
                var invoiced = JSON.parse(el.dataset.invoiced || '[]');
                if (el._ispChart) el._ispChart.destroy();
                el._ispChart = new Chart(el, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Collected',
                                data: collected,
                                borderColor: '#0d9488',
                                backgroundColor: 'rgba(13,148,136,0.12)',
                                fill: true,
                                tension: 0.35,
                            },
                            {
                                label: 'Invoiced',
                                data: invoiced,
                                borderColor: '#f97316',
                                backgroundColor: 'rgba(249,115,22,0.08)',
                                fill: true,
                                tension: 0.35,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { ticks: { color: c.text, maxTicksLimit: 8 }, grid: { color: c.grid } },
                            y: { ticks: { color: c.text }, grid: { color: c.grid } },
                        },
                    },
                });
            }
            function boot() {
                if (typeof Chart === 'undefined') { setTimeout(boot, 80); return; }
                paintOpsChart();
            }
            document.addEventListener('DOMContentLoaded', boot);
            document.addEventListener('livewire:navigated', boot);
        })();
    </script>
</x-filament-widgets::widget>
