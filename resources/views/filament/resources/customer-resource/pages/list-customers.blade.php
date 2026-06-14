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
    $packageOptions = $this->getDirectoryPackageFilterOptions();
    $areaOptions = $this->getDirectoryAreaFilterOptions();
    $ownerOptions = $this->getDirectoryResellerFilterOptions();
    $lineOptions = $this->getDirectoryLineFilterOptions();
    $remainingOptions = $this->getDirectoryRemainingDaysFilterOptions();
    $onuOptions = $this->getDirectoryOnuOwnershipFilterOptions();
    $activeFilterCount = $this->getDirectoryActiveFilterCount();
    $hasSearch = filled($this->tableSearch);
    $activeZone = data_get($this->tableFilters, 'zone_id.value');
    $activeStatus = data_get($this->tableFilters, 'status.value');
    $activePackage = data_get($this->tableFilters, 'package_id.value');
    $activeArea = data_get($this->tableFilters, 'area_id.value');
    $activeOwner = data_get($this->tableFilters, 'reseller_id.value');
    $activeLine = data_get($this->tableFilters, 'network_access_state.value');
    $activeRemaining = data_get($this->tableFilters, 'remaining_days.value');
    $activeOnu = data_get($this->tableFilters, 'onu_ownership.value');
    $filterChips = $this->getDirectoryFilterChips();
    $quickLinks = $this->getDirectoryQuickLinks();
    $heroTitle = $this->getDirectoryHeroTitle();
    $heroSubtitle = $this->getDirectoryHeroSubtitle();
    $variant = $this->getDirectoryPageVariant();
    $billingNavTabs = $this->getDirectoryBillingNavTabs();
    $activePreset = ($preset ?? 'all') !== 'all' ? ($preset ?? 'all') : null;
@endphp

{!! \App\Support\ClientsDirectoryStyles::navigatedScript() !!}
<script src="{{ asset('js/clients-directory-v2.js') }}?v={{ @filemtime(public_path('js/clients-directory-v2.js')) ?: 1 }}" defer></script>

<x-filament-panels::page class="isp-clients-page">
    <div class="cl-dir cl-dir-v2" data-view="table" wire:loading.class="cl-dir-loading">
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

        @if (filled($billingNavTabs))
            <nav class="cl-dir-billing-nav" aria-label="Bill status">
                @foreach ($billingNavTabs as $tab)
                    <a
                        href="{{ $tab['url'] }}"
                        @class(['cl-dir-billing-nav__link', 'cl-dir-billing-nav__link--active' => $tab['active']])
                    >
                        {{ $tab['label'] }}
                        <span class="cl-dir-billing-nav__count">{{ number_format($tab['count']) }}</span>
                    </a>
                @endforeach
            </nav>
        @endif

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

        <section class="cl-dir-command cl-dir-toolbar" aria-label="Search and filters">
            <form wire:submit.prevent="$refresh" id="cl-dir-toolbar-form" class="cl-dir-command__row cl-dir-command__row--search">
                <label class="cl-dir-command__search">
                    <x-filament::icon icon="heroicon-m-magnifying-glass" class="cl-dir-toolbar__icon h-4 w-4" wire:loading.remove wire:target="tableSearch" />
                    <x-filament::loading-indicator class="cl-dir-toolbar__icon h-4 w-4" wire:loading wire:target="tableSearch" />
                    <input
                        type="search"
                        id="cl-dir-search-input"
                        wire:model.live.debounce.500ms="tableSearch"
                        autocomplete="off"
                        maxlength="1000"
                        placeholder="Search name, phone, ID, PPPoE, zone, package, router…"
                    />
                    @if ($hasSearch)
                        <button type="button" wire:click="setDirectorySearch('')" class="cl-dir-toolbar__clear" aria-label="Clear search">&times;</button>
                    @endif
                </label>

                <div class="cl-dir-command__actions">
                    <div class="cl-dir-view-toggle" aria-label="View mode">
                        <button type="button" class="cl-dir-view-toggle__btn cl-dir-view-toggle__btn--active" data-cl-view="table" title="Table view">
                            <x-filament::icon icon="heroicon-m-table-cells" class="h-4 w-4" />
                        </button>
                        <button type="button" class="cl-dir-view-toggle__btn" data-cl-view="cards" title="Card view">
                            <x-filament::icon icon="heroicon-m-squares-2x2" class="h-4 w-4" />
                        </button>
                    </div>

                    <button
                        type="button"
                        class="cl-dir-btn cl-dir-btn--ghost cl-dir-btn--sm"
                        data-cl-filter-toggle
                        aria-expanded="false"
                    >
                        <x-filament::icon icon="heroicon-m-funnel" class="h-4 w-4" />
                        Filters
                        @if ($activeFilterCount > 0)
                            <span class="cl-dir-filter-badge">{{ $activeFilterCount }}</span>
                        @endif
                    </button>

                    <button type="button" wire:click="resetDirectoryToolbar" class="cl-dir-btn cl-dir-btn--ghost cl-dir-btn--sm">
                        Reset
                    </button>
                </div>
            </form>

            <div class="cl-dir-filter-drawer" data-cl-filter-drawer hidden>
                <div class="cl-dir-filter-grid">
                    <label class="cl-dir-toolbar__field">
                        <span class="cl-dir-toolbar__label">Zone</span>
                        <select wire:change="setDirectoryZoneFilter($event.target.value)" class="cl-dir-toolbar__select">
                            <option value="" @selected(blank($activeZone))>All zones</option>
                            @foreach ($zoneOptions as $id => $name)
                                <option value="{{ $id }}" @selected((string) $activeZone === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="cl-dir-toolbar__field">
                        <span class="cl-dir-toolbar__label">Status</span>
                        <select wire:change="setDirectoryStatusFilter($event.target.value)" class="cl-dir-toolbar__select">
                            <option value="" @selected(blank($activeStatus))>Any status</option>
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected((string) $activeStatus === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="cl-dir-toolbar__field">
                        <span class="cl-dir-toolbar__label">Package</span>
                        <select wire:change="setDirectoryPackageFilter($event.target.value)" class="cl-dir-toolbar__select">
                            <option value="" @selected(blank($activePackage))>All packages</option>
                            @foreach ($packageOptions as $id => $name)
                                <option value="{{ $id }}" @selected((string) $activePackage === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="cl-dir-toolbar__field">
                        <span class="cl-dir-toolbar__label">Area</span>
                        <select wire:change="setDirectoryAreaFilter($event.target.value)" class="cl-dir-toolbar__select">
                            <option value="" @selected(blank($activeArea))>All areas</option>
                            @foreach ($areaOptions as $id => $name)
                                <option value="{{ $id }}" @selected((string) $activeArea === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="cl-dir-toolbar__field">
                        <span class="cl-dir-toolbar__label">Owner</span>
                        <select wire:change="setDirectoryResellerFilter($event.target.value)" class="cl-dir-toolbar__select">
                            <option value="" @selected(blank($activeOwner))>All owners</option>
                            @foreach ($ownerOptions as $id => $name)
                                <option value="{{ $id }}" @selected((string) $activeOwner === (string) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="cl-dir-toolbar__field">
                        <span class="cl-dir-toolbar__label">Line</span>
                        <select wire:change="setDirectoryLineFilter($event.target.value)" class="cl-dir-toolbar__select">
                            <option value="" @selected(blank($activeLine))>Any line</option>
                            @foreach ($lineOptions as $value => $label)
                                <option value="{{ $value }}" @selected((string) $activeLine === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="cl-dir-toolbar__field">
                        <span class="cl-dir-toolbar__label">Expiry window</span>
                        <select wire:change="setDirectoryRemainingDaysFilter($event.target.value)" class="cl-dir-toolbar__select">
                            <option value="" @selected(blank($activeRemaining))>Any</option>
                            @foreach ($remainingOptions as $value => $label)
                                <option value="{{ $value }}" @selected((string) $activeRemaining === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="cl-dir-toolbar__field">
                        <span class="cl-dir-toolbar__label">ONU ownership</span>
                        <select wire:change="setDirectoryOnuOwnershipFilter($event.target.value)" class="cl-dir-toolbar__select">
                            <option value="" @selected(blank($activeOnu))>Any</option>
                            @foreach ($onuOptions as $value => $label)
                                <option value="{{ $value }}" @selected((string) $activeOnu === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </div>

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

        <div class="cl-dir-kpi-strip cl-dir-stats">
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
