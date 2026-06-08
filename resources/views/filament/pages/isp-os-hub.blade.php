{!! \App\Support\IspOsStyles::navigatedScript() !!}

<x-filament-panels::page class="isp-os-hub-page">
    <link rel="stylesheet" href="{{ asset('css/isp-os-executive.css') }}?v={{ @filemtime(public_path('css/isp-os-executive.css')) ?: 1 }}">

    <div class="ios-pro space-y-5" wire:loading.class="ios-pro-loading">
        <header class="ios-hero">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest opacity-85">ISP Operating System</p>
                <h1 class="ios-hero__title">Executive command center</h1>
                <p class="ios-hero__sub">Billing · CRM · Network · GIS · NOC · Finance · HR · Inventory · AI — unified ISP OS.</p>
            </div>
            <div class="ios-hero__score">
                <span class="text-xs uppercase tracking-wide opacity-85">Network health</span>
                <strong>{{ $intel['network_health_score'] ?? 0 }}%</strong>
                <span class="text-xs opacity-85">{{ number_format($intel['revenue_today'] ?? 0) }} collected today</span>
            </div>
        </header>

        <section aria-label="Global search">
            <input type="search" wire:model.live.debounce.300ms="globalSearch" class="ios-search" placeholder="Search customer, invoice, ONU, OLT, router, ticket, employee, task…" />
            @if ($searchResults !== [])
                <div class="ios-search-results">
                    @foreach ($searchResults as $hit)
                        <a href="{{ $hit['url'] }}" class="ios-search-hit">
                            <span>{{ $hit['label'] }}</span>
                            <span class="ios-search-hit__group">{{ $hit['group'] }}{{ !empty($hit['meta']) ? ' · '.$hit['meta'] : '' }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="ios-os-tabs">
            @foreach (['executive' => 'Executive', 'network' => 'Network', 'operations' => 'Operations'] as $tab => $label)
                <button type="button" wire:click="setTab('{{ $tab }}')" @class(['ios-os-tab', 'ios-os-tab--active' => $activeTab === $tab])>{{ $label }}</button>
            @endforeach
            <button type="button" wire:click="refreshExecutive" class="ios-os-tab ml-auto" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="refreshExecutive">↻ Refresh</span>
                <span wire:loading wire:target="refreshExecutive">…</span>
            </button>
        </div>

        @if ($activeTab === 'executive')
            <section aria-label="Executive KPIs">
                <div class="ios-exec-kpi-grid">
                    @foreach ($executiveKpis as $kpi)
                        <article class="ios-exec-kpi">
                            <span>{{ $kpi['label'] }}</span>
                            <strong>
                                @if (($kpi['format'] ?? '') === 'money')
                                    {{ number_format($kpi['value'], 0) }}
                                @elseif (($kpi['format'] ?? '') === 'percent')
                                    {{ $kpi['value'] }}%
                                @else
                                    {{ number_format($kpi['value']) }}
                                @endif
                            </strong>
                        </article>
                    @endforeach
                </div>
            </section>

            <section aria-label="Command centers">
                <div class="ios-panel__head" style="margin-bottom:0.65rem;border:0;padding:0;">Command centers</div>
                <div class="ios-cmd-grid">
                    @foreach ($commandCenters as $hub)
                        <a href="{{ $hub['url'] }}" class="ios-cmd-card ios-cmd-card--{{ $hub['tone'] }}">
                            <x-filament::icon :icon="'heroicon-o-'.$hub['icon']" class="h-6 w-6" />
                            <div>
                                <strong>{{ $hub['title'] }}</strong>
                                <span>{{ $hub['desc'] }}</span>
                            </div>
                            @if (!empty($hub['badge']))
                                <em>{{ $hub['badge'] }}</em>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($activeTab === 'network' || $activeTab === 'executive')
            @if ($activeTab === 'network')
                <section aria-label="Network KPIs">
                    <div class="ios-kpi-grid">
                        @foreach ($networkKpis as $kpi)
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
            @endif

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
        @endif

        @if ($activeTab === 'operations' || $activeTab === 'executive')
            @if ($activeTab === 'operations')
                <section aria-label="Operations modules">
                    <div class="ios-panel__head" style="margin-bottom:0.65rem;border:0;padding:0;">Network & field operations</div>
                    <div class="ios-modules">
                        @foreach ($operationsModules as $mod)
                            <a href="{{ $mod['url'] }}" class="ios-module-card">
                                <div class="ios-module-card__title">{{ $mod['title'] }}</div>
                                <div class="ios-module-card__desc">{{ $mod['desc'] }}</div>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        @endif

        <nav class="ios-mobile-bar" aria-label="Mobile ISP OS shortcuts">
            @foreach ($mobileLinks as $link)
                <a href="{{ $link['url'] }}">
                    <x-filament::icon :icon="'heroicon-o-'.$link['icon']" class="h-5 w-5" />
                    <span>{{ $link['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </div>
</x-filament-panels::page>
