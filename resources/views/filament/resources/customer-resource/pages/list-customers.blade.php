@php
    $statCards = $this->directoryStatCards;
    $tabs = $this->directoryPresetTabs ?: [
        ['key' => 'all', 'label' => 'All', 'count' => 0],
        ['key' => 'online', 'label' => 'Online', 'count' => 0],
        ['key' => 'offline', 'label' => 'Offline', 'count' => 0],
        ['key' => 'home', 'label' => 'Home', 'count' => 0],
        ['key' => 'reseller', 'label' => 'Reseller', 'count' => 0],
    ];
    $indexUrl = \App\Filament\Resources\CustomerResource::getUrl('index');
    $createUrl = \App\Filament\Resources\CustomerResource::getUrl('create');
    $canExport = \App\Filament\Pages\ExportClientsReport::canAccess();
    $exportUrl = $canExport ? \App\Filament\Pages\ExportClientsReport::getUrl() : null;
    $zoneOptions = $this->getDirectoryZoneFilterOptions();
    $statusOptions = $this->getDirectoryStatusFilterOptions();
    $hasSearch = filled($this->tableSearch);
    $activeZone = data_get($this->tableFilters, 'zone_id.value');
    $activeStatus = data_get($this->tableFilters, 'status.value');
    $filterChips = $this->getDirectoryFilterChips();
    $quickLinks = $this->getDirectoryQuickLinks();
    $heroTitle = $this->getDirectoryHeroTitle();
    $heroSubtitle = $this->getDirectoryHeroSubtitle();
    $variant = $this->getDirectoryPageVariant();
    $activePreset = ($preset ?? 'all') !== 'all' ? ($preset ?? 'all') : null;
    $listQuery = [];
    if ($activePreset) {
        $listQuery['preset'] = $activePreset;
    }
    if (filled($activeZone)) {
        $listQuery['tableFilters']['zone_id']['value'] = $activeZone;
    }
    if (filled($activeStatus)) {
        $listQuery['tableFilters']['status']['value'] = $activeStatus;
    }
    if (filled($this->tableSearch)) {
        $listQuery['tableSearch'] = $this->tableSearch;
    }
    $resetUrl = $indexUrl.($activePreset ? '?preset='.$activePreset : '');
    $clearSearchQuery = $listQuery;
    unset($clearSearchQuery['tableSearch']);
    $clearSearchUrl = $indexUrl.(filled($clearSearchQuery) ? '?'.http_build_query($clearSearchQuery) : '');
@endphp

{!! \App\Support\ClientsDirectoryStyles::navigatedScript() !!}

<x-filament-panels::page class="isp-clients-page">
    <div class="cl-dir">
        <header class="cl-dir-hero">
            <div class="cl-dir-hero__body">
                <span class="cl-dir-hero__badge">
                    <span class="cl-dir-hero__badge-dot" aria-hidden="true"></span>
                    Clients directory
                </span>
                <h1 class="cl-dir-hero__title">{{ $heroTitle }}</h1>
                @if (filled($heroSubtitle))
                    <p class="cl-dir-hero__sub">{{ $heroSubtitle }}</p>
                @endif
            </div>
            <div class="cl-dir-hero__actions">
                <a href="{{ \App\Filament\Pages\ClientsHub::getUrl() }}" class="cl-dir-btn cl-dir-btn--ghost cl-dir-btn--sm">
                    <x-filament::icon icon="heroicon-m-squares-2x2" class="h-4 w-4" />
                    Hub
                </a>
                @if ($variant === null)
                    <a href="{{ $indexUrl }}" @class(['cl-dir-btn cl-dir-btn--ghost cl-dir-btn--sm', 'cl-dir-btn--active' => ($preset ?? 'all') === 'all'])>
                        All
                    </a>
                    <a href="{{ \App\Filament\Resources\CustomerResource::getUrl('due') }}" class="cl-dir-btn cl-dir-btn--ghost cl-dir-btn--sm">
                        Due
                    </a>
                    <a href="{{ \App\Filament\Resources\CustomerResource::getUrl('vip') }}" class="cl-dir-btn cl-dir-btn--ghost cl-dir-btn--sm">
                        VIP
                    </a>
                @endif
                <a href="{{ $createUrl }}" class="cl-dir-btn cl-dir-btn--primary cl-dir-btn--sm">
                    <x-filament::icon icon="heroicon-m-plus" class="h-4 w-4" />
                    Add Client
                </a>
            </div>
        </header>

        <section class="cl-dir-quick" aria-label="Quick links">
            @foreach ($quickLinks as $link)
                <a href="{{ $link['url'] }}" class="cl-dir-quick__item cl-dir-quick__item--{{ $link['tone'] }}">
                    <span class="cl-dir-quick__icon">
                        <x-filament::icon :icon="$link['icon']" class="h-4 w-4" />
                    </span>
                    <span class="cl-dir-quick__body">
                        <span class="cl-dir-quick__label">{{ $link['label'] }}</span>
                        <strong class="cl-dir-quick__count">{{ $link['count'] }}</strong>
                    </span>
                </a>
            @endforeach
        </section>

        <div class="cl-dir-actions no-print">
            @if ($variant === null)
                <nav class="cl-dir-tabs" aria-label="Quick presets">
                    @foreach ($tabs as $tab)
                        <a
                            href="{{ $indexUrl }}?preset={{ $tab['key'] }}"
                            @class(['cl-dir-tab', 'cl-dir-tab--active' => ($preset ?? 'all') === $tab['key']])
                        >{{ $tab['label'] }} <span class="cl-dir-tab__count">{{ number_format($tab['count']) }}</span></a>
                    @endforeach
                </nav>
            @endif
            <div class="cl-dir-actions__right">
                @foreach ($this->getCachedHeaderActions() as $action)
                    {{ $action }}
                @endforeach
            </div>
        </div>

        <section class="cl-dir-toolbar">
            <div class="cl-dir-toolbar__head">
                <p class="cl-dir-toolbar__title">Search &amp; filter clients</p>
            </div>

            <form method="GET" action="{{ $indexUrl }}" id="cl-dir-toolbar-form">
                @if ($activePreset)
                    <input type="hidden" name="preset" value="{{ $activePreset }}">
                @endif

                <div class="cl-dir-toolbar__search">
                    <x-filament::icon icon="heroicon-m-magnifying-glass" class="cl-dir-toolbar__icon h-4 w-4" />
                    <input
                        type="search"
                        id="cl-dir-search-input"
                        name="tableSearch"
                        value="{{ $this->tableSearch }}"
                        autocomplete="off"
                        maxlength="1000"
                        placeholder="Search name, phone, ID, PPPoE, zone…"
                        class="cl-dir-toolbar__input"
                        oninput="window.clearTimeout(window._clDirSearchTimer); window._clDirSearchTimer = window.setTimeout(function () { document.getElementById('cl-dir-toolbar-form').submit(); }, 500);"
                    />
                    @if ($hasSearch)
                        <a href="{{ $clearSearchUrl }}" class="cl-dir-toolbar__clear" aria-label="Clear search">&times;</a>
                    @endif
                </div>

                <div class="cl-dir-toolbar__filters">
                    <label class="cl-dir-toolbar__field">
                        <span class="cl-dir-toolbar__label">Zone</span>
                        <select
                            name="tableFilters[zone_id][value]"
                            class="cl-dir-toolbar__select"
                            onchange="this.form.submit()"
                        >
                            <option value="" @selected(blank($activeZone))>All zones</option>
                            @foreach ($zoneOptions as $id => $name)
                                <option value="{{ $id }}" @selected((string) $activeZone === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="cl-dir-toolbar__field">
                        <span class="cl-dir-toolbar__label">Status</span>
                        <select
                            name="tableFilters[status][value]"
                            class="cl-dir-toolbar__select"
                            onchange="this.form.submit()"
                        >
                            <option value="" @selected(blank($activeStatus))>Any status</option>
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected((string) $activeStatus === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <a href="{{ $resetUrl }}" class="cl-dir-btn cl-dir-btn--ghost cl-dir-btn--sm cl-dir-toolbar__reset">
                        Reset
                    </a>
                </div>
            </form>

            <div class="cl-dir-toolbar__meta">
                <p class="cl-dir-toolbar__result">
                    {{ $this->getDirectoryResultSummary() }}
                </p>
                @if ($filterChips !== [])
                    <div class="cl-dir-chips" aria-label="Active filters">
                        @foreach ($filterChips as $chip)
                            <a href="{{ $this->getDirectoryFilterChipUrl($chip['key']) }}" class="cl-dir-chip">
                                {{ $chip['label'] }}
                                <span aria-hidden="true">&times;</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <div class="cl-dir-stats">
            @foreach ($statCards as $card)
                @php
                    $tag = ! empty($card['url']) ? 'a' : 'article';
                @endphp
                <{{ $tag }}
                    @if (! empty($card['url'])) href="{{ $card['url'] }}" @endif
                    @class(['cl-dir-stat', 'cl-dir-stat--'.$card['tone'], 'cl-dir-stat--link' => ! empty($card['url'])])
                >
                    <div class="cl-dir-stat__body">
                        <span class="cl-dir-stat__label">{{ $card['label'] }}</span>
                        <strong class="cl-dir-stat__value">{{ $card['value'] }}</strong>
                        @if (! empty($card['hint']))
                            <span class="cl-dir-stat__hint">{{ $card['hint'] }}</span>
                        @endif
                    </div>
                    <span class="cl-dir-stat__icon" aria-hidden="true">
                        <x-filament::icon :icon="$card['icon']" class="h-5 w-5" />
                    </span>
                </{{ $tag }}>
            @endforeach
        </div>

        <section class="cl-dir-table">
            @if ($canExport && $exportUrl)
                <div class="cl-dir-table-export no-print">
                    <a href="{{ $exportUrl }}" class="cl-dir-btn cl-dir-btn--ghost cl-dir-btn--sm">
                        <x-filament::icon icon="heroicon-m-arrow-down-tray" class="h-4 w-4" />
                        Export
                    </a>
                </div>
            @endif
            {{ $this->table }}
        </section>
    </div>
</x-filament-panels::page>
