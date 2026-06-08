@php
    $intel = $this->getIntelligence();
    $kpis = $this->getKpiCards();
    $insights = $intel['insights'] ?? [];
    $rca = $this->getRootCauses();
    $timeline = $this->getTimeline();
    $searchResults = $this->getSearchResults();
@endphp

{!! \App\Support\IspOsStyles::navigatedScript() !!}

<x-filament-panels::page class="isp-os-hub-page">
    <div class="ios-pro space-y-5" wire:loading.class="ios-pro-loading">
        <header class="ios-hero">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest opacity-85">ISP Operating System</p>
                <h1 class="ios-hero__title">Operations command center</h1>
                <p class="ios-hero__sub">Billing · CRM · MikroTik · OLT · GIS · NOC · faults · field ops · revenue — unified ISP OS.</p>
            </div>
            <div class="ios-hero__score">
                <span class="text-xs uppercase tracking-wide opacity-85">Network health</span>
                <strong>{{ $intel['network_health_score'] ?? 0 }}%</strong>
                <span class="text-xs opacity-85">{{ number_format($intel['revenue_today'] ?? 0) }} collected today</span>
            </div>
        </header>

        <section aria-label="Global search">
            <input type="search" wire:model.live.debounce.300ms="globalSearch" class="ios-search" placeholder="Search customer, ONU, OLT, router, ticket, splitter…" />
            @if ($searchResults !== [])
                <div class="ios-search-results">
                    @foreach ($searchResults as $hit)
                        <a href="{{ $hit['url'] }}" class="ios-search-hit">
                            <span>{{ $hit['label'] }}</span>
                            <span class="ios-search-hit__group">{{ $hit['group'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section aria-label="Operations KPIs">
            <div class="ios-kpi-grid">
                @foreach ($kpis as $kpi)
                    @php $tag = ! empty($kpi['url']) ? 'a' : 'article'; @endphp
                    <{{ $tag }} @if (! empty($kpi['url'])) href="{{ $kpi['url'] }}" @endif @class(['ios-kpi', 'ios-kpi--'.$kpi['tone']])>
                        <span class="ios-kpi__label">{{ $kpi['label'] }}</span>
                        <strong class="ios-kpi__value">{{ $kpi['value'] }}</strong>
                        @if (! empty($kpi['hint']))
                            <span class="ios-kpi__hint">{{ $kpi['hint'] }}</span>
                        @endif
                    </{{ $tag }}>
                @endforeach
            </div>
        </section>

        @if ($insights !== [])
            <section class="ios-panel" aria-label="AI insights">
                <div class="ios-panel__head">Operational insights</div>
                <div style="padding:0.75rem;display:grid;gap:0.5rem;">
                    @foreach ($insights as $insight)
                        <div class="ios-insight">
                            <span class="isp-status-dot isp-status-dot--{{ $insight['tone'] === 'critical' ? 'offline' : 'warning' }}"></span>
                            <span>{{ $insight['message'] }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section aria-label="Modules">
            <div class="ios-panel__head" style="margin-bottom:0.65rem;border:0;padding:0;">Platform modules</div>
            <div class="ios-modules">
                @foreach ($this->getModuleCards() as $mod)
                    <a href="{{ $mod['url'] }}" class="ios-module-card">
                        <div class="ios-module-card__title">{{ $mod['title'] }}</div>
                        <div class="ios-module-card__desc">{{ $mod['desc'] }}</div>
                    </a>
                @endforeach
            </div>
        </section>

        <div class="grid gap-5 lg:grid-cols-2">
            @if ($rca !== [])
                <section class="ios-panel" aria-label="Root cause">
                    <div class="ios-panel__head">Root cause analysis</div>
                    <div style="padding:0.75rem;display:grid;gap:0.5rem;">
                        @foreach ($rca as $item)
                            <div class="ios-rca">
                                <span class="ios-rca__badge">{{ str_replace('_', ' ', $item['root_cause']) }}</span>
                                <span style="margin-left:0.35rem;font-size:0.7rem;color:var(--ios-muted);">{{ round($item['confidence'] * 100) }}% confidence</span>
                                <p style="margin:0.35rem 0 0;">{{ $item['message'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="ios-panel" aria-label="Timeline">
                <div class="ios-panel__head">Network timeline</div>
                <div class="ios-timeline" style="padding:0.75rem 1rem;">
                    @forelse ($timeline as $event)
                        <div @class(['ios-timeline__item', 'ios-timeline__item--'.$event['severity']])>
                            <strong>{{ $event['title'] }}</strong>
                            <span style="display:block;font-size:0.72rem;color:var(--ios-muted);">{{ $event['at'] }} · {{ $event['type'] }}</span>
                        </div>
                    @empty
                        <p style="font-size:0.82rem;color:var(--ios-muted);">No recent network events.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
