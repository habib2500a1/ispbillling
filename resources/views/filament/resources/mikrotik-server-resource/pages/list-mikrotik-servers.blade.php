@php
    $stats = $this->getNetworkFleetStats();
    $statCards = $this->getNetworkStatCards();
    $filterChips = $this->getNetworkFilterChips();
    $activeFilterCount = $this->getNetworkActiveFilterCount();
    $indexUrl = \App\Filament\Resources\MikrotikServerResource::getUrl('index');
    $createUrl = \App\Filament\Resources\MikrotikServerResource::getUrl('create');
    $hubUrl = \App\Filament\Pages\NetworkIntelligenceHub::getUrl();
    $dockLinks = $this->getNetworkDockLinks();
@endphp

{!! \App\Support\NetworkStyles::navigatedScript() !!}
<script src="{{ asset('js/network-routers-v2.js') }}?v={{ @filemtime(public_path('js/network-routers-v2.js')) ?: 1 }}" defer></script>

<x-filament-panels::page class="isp-network-page isp-network-routers-page">
    <div class="nr-pro" data-network-list data-view="table" wire:loading.class="nr-pro-loading">
        <header class="nr-inv-hero">
            <div class="nr-inv-hero__body">
                <span class="nr-inv-hero__badge">Network operations</span>
                <h1 class="nr-inv-hero__title">Routers list</h1>
                <p class="nr-inv-hero__sub">
                    MikroTik RouterOS servers for PPPoE sync, live sessions, subscriber import, and monitoring.
                </p>
            </div>
            <div class="nr-inv-hero__actions">
                <a href="{{ $hubUrl }}" class="nr-inv-btn nr-inv-btn--ghost nr-inv-btn--sm">
                    <x-filament::icon icon="heroicon-m-cpu-chip" class="h-4 w-4" />
                    NOC center
                </a>
                <a href="{{ $createUrl }}" class="nr-inv-btn nr-inv-btn--primary nr-inv-btn--sm">
                    <x-filament::icon icon="heroicon-m-plus" class="h-4 w-4" />
                    Add router
                </a>
            </div>
        </header>

        <div class="nr-inv-stats">
            @foreach ($statCards as $card)
                @php $tag = ! empty($card['url']) ? 'a' : 'article'; @endphp
                <{{ $tag }}
                    @if (! empty($card['url'])) href="{{ $card['url'] }}" @endif
                    @class(['nr-inv-stat', 'nr-inv-stat--'.$card['tone']])
                >
                    <span class="nr-inv-stat__label">{{ $card['label'] }}</span>
                    <strong class="nr-inv-stat__value">{{ $card['value'] }}</strong>
                    @if (! empty($card['hint']))
                        <span style="font-size:0.68rem;color:var(--nr-muted);">{{ $card['hint'] }}</span>
                    @endif
                </{{ $tag }}>
            @endforeach
        </div>

        <section class="nr-inv-toolbar" aria-label="Search and filters">
            <form method="GET" action="{{ $indexUrl }}" id="nr-inv-toolbar-form" class="nr-inv-toolbar__row">
                <label class="nr-inv-search">
                    <x-filament::icon icon="heroicon-m-magnifying-glass" class="h-4 w-4" style="color:var(--nr-muted);" />
                    <input
                        type="search"
                        name="tableSearch"
                        value="{{ $this->tableSearch }}"
                        autocomplete="off"
                        maxlength="255"
                        placeholder="Router name, IP, host…"
                        oninput="window.clearTimeout(window._nrSearchTimer); window._nrSearchTimer = window.setTimeout(function () { document.getElementById('nr-inv-toolbar-form').requestSubmit(); }, 350);"
                    />
                </label>

                <div class="nr-inv-view-toggle" aria-label="View mode">
                    <button type="button" class="nr-inv-view-toggle__btn nr-inv-view-toggle__btn--active" data-nr-view="table" title="Table">
                        <x-filament::icon icon="heroicon-m-table-cells" class="h-4 w-4" />
                    </button>
                    <button type="button" class="nr-inv-view-toggle__btn" data-nr-view="cards" title="Cards">
                        <x-filament::icon icon="heroicon-m-squares-2x2" class="h-4 w-4" />
                    </button>
                </div>

                <button type="button" wire:click="resetNetworkToolbar" class="nr-inv-btn nr-inv-btn--ghost nr-inv-btn--sm">
                    Reset
                </button>
            </form>

            @if ($filterChips !== [])
                <div class="nr-inv-chips">
                    @foreach ($filterChips as $chip)
                        <a href="{{ $this->getNetworkFilterChipUrl($chip['key']) }}" class="nr-inv-chip">
                            {{ $chip['label'] }}
                            <x-filament::icon icon="heroicon-m-x-mark" class="h-3 w-3" />
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="nr-inv-table-card">
            <div style="padding:0.75rem 1rem;border-bottom:1px solid var(--nr-border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
                <strong style="color:var(--nr-text);">Registered routers</strong>
                <span style="font-size:0.78rem;color:var(--nr-muted);">
                    {{ number_format($stats['online']) }} online · {{ number_format($stats['offline']) }} offline
                </span>
            </div>
            {{ $this->table }}
            <div data-nr-mobile-cards aria-hidden="true"></div>
        </section>

        <nav class="nr-inv-dock" aria-label="Network quick nav">
            @foreach ($dockLinks as $link)
                <a href="{{ $link['url'] }}" @class(['nr-inv-dock__link', 'nr-inv-dock__link--active' => ! empty($link['active'])])>
                    <x-filament::icon :icon="$link['icon']" class="h-5 w-5" />
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>
    </div>
</x-filament-panels::page>
