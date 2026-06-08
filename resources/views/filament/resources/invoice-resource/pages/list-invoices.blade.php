@php
    $variant = $this->getBillingPageVariant();
    $navTabs = $this->getBillingNavTabs();
    $statCards = $this->getBillingStatCards();
    $filterChips = $this->getBillingFilterChips();
    $activeFilterCount = $this->getBillingActiveFilterCount();
    $hasSearch = filled($this->tableSearch);
    $indexUrl = match ($variant) {
        'due' => \App\Filament\Resources\InvoiceResource::getUrl('due'),
        'paid' => \App\Filament\Resources\InvoiceResource::getUrl('paid'),
        default => \App\Filament\Resources\InvoiceResource::getUrl('index'),
    };
    $createUrl = \App\Filament\Resources\InvoiceResource::getUrl('create');
    $hubUrl = \App\Filament\Pages\BillingOverview::getUrl();
    $collectUrl = \App\Filament\Pages\BillCollectionDesk::getUrl();
    $dockLinks = $this->getBillingDockLinks();
@endphp

{!! \App\Support\BillingStyles::navigatedScript() !!}
<script src="{{ asset('js/billing-invoices-v2.js') }}?v={{ @filemtime(public_path('js/billing-invoices-v2.js')) ?: 1 }}" defer></script>

<x-filament-panels::page class="isp-billing-invoices-page">
    <div
        class="bl-pro"
        data-view="table"
        wire:loading.class="bl-pro-loading"
        data-bl-saved-filters='@json($this->getBillingSavedFilters())'
    >
        <header class="bl-inv-hero">
            <div class="bl-inv-hero__body">
                <span class="bl-inv-hero__badge">Billing &amp; invoices</span>
                <h1 class="bl-inv-hero__title">{{ $this->getBillingHeroTitle() }}</h1>
                <p class="bl-inv-hero__sub">{{ $this->getBillingHeroSubtitle() }}</p>
            </div>
            <div class="bl-inv-hero__actions">
                <a href="{{ $hubUrl }}" class="bl-inv-btn bl-inv-btn--ghost bl-inv-btn--sm">
                    <x-filament::icon icon="heroicon-m-squares-2x2" class="h-4 w-4" />
                    Center
                </a>
                <a href="{{ $collectUrl }}" class="bl-inv-btn bl-inv-btn--ghost bl-inv-btn--sm">
                    <x-filament::icon icon="heroicon-m-currency-bangladeshi" class="h-4 w-4" />
                    Collect
                </a>
                <a href="{{ $createUrl }}" class="bl-inv-btn bl-inv-btn--primary bl-inv-btn--sm">
                    <x-filament::icon icon="heroicon-m-plus" class="h-4 w-4" />
                    New invoice
                </a>
            </div>
        </header>

        <nav class="bl-inv-nav" aria-label="Bill lists">
            @foreach ($navTabs as $tab)
                <a
                    href="{{ $tab['url'] }}"
                    @class(['bl-inv-nav__link', 'bl-inv-nav__link--active' => $tab['active']])
                >
                    {{ $tab['label'] }}
                    <span class="bl-inv-nav__count">{{ number_format($tab['count']) }}</span>
                </a>
            @endforeach
        </nav>

        <div class="bl-inv-stats">
            @foreach ($statCards as $card)
                @php $tag = ! empty($card['url']) ? 'a' : 'article'; @endphp
                <{{ $tag }}
                    @if (! empty($card['url'])) href="{{ $card['url'] }}" @endif
                    @class(['bl-inv-stat', 'bl-inv-stat--'.$card['tone']])
                >
                    <span class="bl-inv-stat__label">{{ $card['label'] }}</span>
                    <strong class="bl-inv-stat__value">{{ $card['value'] }}</strong>
                    @if (! empty($card['hint']))
                        <span class="bl-inv-stat__hint" style="font-size:0.68rem;color:var(--bl-muted);">{{ $card['hint'] }}</span>
                    @endif
                </{{ $tag }}>
            @endforeach
        </div>

        <div class="bl-bulk-bar" data-bl-bulk-bar aria-live="polite">
            <span><span data-bl-bulk-count>0</span> selected</span>
            <button type="button" class="bl-bulk-bar__btn" data-bl-bulk-print>Print</button>
            <button type="button" class="bl-bulk-bar__btn" data-bl-bulk-export style="background:#0f766e;">Export</button>
        </div>

        <section class="bl-inv-toolbar" aria-label="Search and filters">
            <div class="bl-saved-filters" data-bl-saved-filters aria-label="Saved filters"></div>

            <form method="GET" action="{{ $indexUrl }}" id="bl-inv-toolbar-form" class="bl-inv-toolbar__row">
                <label class="bl-inv-search">
                    <x-filament::icon icon="heroicon-m-magnifying-glass" class="h-4 w-4" style="color:var(--bl-muted);" />
                    <input
                        type="search"
                        name="tableSearch"
                        value="{{ $this->tableSearch }}"
                        autocomplete="off"
                        maxlength="1000"
                        placeholder="Invoice #, customer name, ID, phone…"
                        oninput="window.clearTimeout(window._blInvSearchTimer); window._blInvSearchTimer = window.setTimeout(function () { document.getElementById('bl-inv-toolbar-form').requestSubmit(); }, 400);"
                    />
                </label>

                <div class="bl-inv-view-toggle" aria-label="View mode">
                    <button type="button" class="bl-inv-view-toggle__btn bl-inv-view-toggle__btn--active" data-bl-view="table" title="Table">
                        <x-filament::icon icon="heroicon-m-table-cells" class="h-4 w-4" />
                    </button>
                    <button type="button" class="bl-inv-view-toggle__btn" data-bl-view="cards" title="Cards">
                        <x-filament::icon icon="heroicon-m-squares-2x2" class="h-4 w-4" />
                    </button>
                </div>

                <button type="button" wire:click="resetBillingToolbar" class="bl-inv-btn bl-inv-btn--ghost bl-inv-btn--sm" style="color:var(--bl-text);border-color:var(--bl-border);background:var(--bl-card);">
                    Reset
                </button>
            </form>

            <div class="bl-inv-toolbar__row" style="justify-content:space-between;">
                <p style="margin:0;font-size:0.78rem;font-weight:600;color:var(--bl-muted);">
                    {{ $this->getBillingResultSummary() }}
                    @if ($activeFilterCount > 0)
                        · {{ $activeFilterCount }} filter{{ $activeFilterCount > 1 ? 's' : '' }}
                    @endif
                </p>
                <div class="bl-inv-table-actions no-print">
                    @foreach ($this->getCachedHeaderActions() as $action)
                        {{ $action }}
                    @endforeach
                </div>
            </div>

            @if ($filterChips !== [])
                <div class="bl-inv-chips" aria-label="Active filters">
                    @foreach ($filterChips as $chip)
                        <a href="{{ $this->getBillingFilterChipUrl($chip['key']) }}" class="bl-inv-chip">
                            {{ $chip['label'] }}
                            <span aria-hidden="true">&times;</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="bl-inv-table-wrap">
            <div class="bl-inv-mobile-cards" data-bl-mobile-cards aria-hidden="true"></div>
            {{ $this->table }}
        </section>

        <nav class="bl-inv-dock" aria-label="Billing quick nav">
            <div class="bl-inv-dock__inner">
                @foreach ($dockLinks as $link)
                    @if (filled($link['url'] ?? null))
                        <a
                            href="{{ $link['url'] }}"
                            @class(['bl-inv-dock__link', 'bl-inv-dock__link--active' => ! empty($link['active'])])
                        >
                            <x-filament::icon :icon="$link['icon']" />
                            <span>{{ $link['label'] }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        </nav>
    </div>
</x-filament-panels::page>
