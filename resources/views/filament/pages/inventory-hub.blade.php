@php
    $s = $summary ?? [];
    $fmt = fn ($n) => number_format((float) $n, 0);
@endphp

<x-filament-panels::page class="isp-inventory-hub-page">
    <div class="iv-pro">
        <header class="iv-hero">
            <div class="iv-hero__grid">
                <span class="iv-hero__badge">Stock · POS · COGS</span>
                <h1 class="iv-hero__title">Inventory Pro</h1>
                <p class="iv-hero__sub">
                    Multi-warehouse stock · barcode POS · purchase orders · retail sales · invoice hardware lines · public shop &amp; device inventory.
                </p>
                <div class="iv-hero__actions">
                    <a href="{{ \App\Filament\Resources\InventorySaleResource::getUrl('create') }}" class="iv-btn iv-btn--white">
                        <x-filament::icon icon="heroicon-m-qr-code" class="h-4 w-4" />
                        New sale (POS)
                    </a>
                    <a href="{{ \App\Filament\Resources\ProductResource::getUrl() }}" class="iv-btn iv-btn--glass">
                        <x-filament::icon icon="heroicon-m-shopping-bag" class="h-4 w-4" />
                        Products
                    </a>
                    <a href="{{ \App\Filament\Resources\WarehouseResource::getUrl() }}" class="iv-btn iv-btn--glass">
                        <x-filament::icon icon="heroicon-m-building-library" class="h-4 w-4" />
                        Warehouses
                    </a>
                </div>
            </div>
            <div class="iv-hero__highlight">
                <div class="iv-hero__highlight-card">
                    <span class="iv-hero__highlight-label">Stock value (cost)</span>
                    <strong class="iv-hero__highlight-value">{{ $fmt($s['stock_value'] ?? 0) }} BDT</strong>
                    <span class="iv-hero__highlight-hint">{{ $fmt($s['stock_units'] ?? 0) }} units · {{ $s['product_count'] ?? 0 }} SKUs · {{ $s['warehouse_count'] ?? 0 }} warehouses</span>
                </div>
            </div>
        </header>

        <div class="iv-stats">
            @foreach ($this->getKpiCards() as $kpi)
                <a
                    href="{{ $kpi['url'] }}"
                    @class([
                        'iv-stat iv-stat--' . $kpi['tone'],
                        'iv-stat--alert' => ! empty($kpi['alert']),
                    ])
                    @if (! empty($kpi['external'])) target="_blank" rel="noopener" @endif
                >
                    <div class="iv-stat__row">
                        <span class="iv-stat__icon">
                            <x-filament::icon :icon="$kpi['icon']" class="h-5 w-5" />
                        </span>
                    </div>
                    <span class="iv-stat__label">{{ $kpi['label'] }}</span>
                    <strong class="iv-stat__value">{{ $kpi['value'] }}</strong>
                    <span class="iv-stat__hint">{{ $kpi['hint'] }}</span>
                </a>
            @endforeach
        </div>

        <section>
            <div class="iv-section__head">
                <div>
                    <h2 class="iv-section__title">Modules &amp; workflows</h2>
                    <p class="iv-section__sub">Warehouses · buying · selling · accounting · network gear</p>
                </div>
            </div>
            <div class="iv-bento">
                @foreach ($this->getActionCards() as $card)
                    <a
                        href="{{ $card['url'] }}"
                        @class([
                            'iv-tile iv-tile--' . $card['tone'],
                            'iv-tile--featured' => ! empty($card['featured']),
                        ])
                        @if (! empty($card['external'])) target="_blank" rel="noopener" @endif
                    >
                        <div class="iv-tile__head">
                            <span class="iv-tile__icon">
                                <x-filament::icon :icon="$card['icon']" class="h-6 w-6" />
                            </span>
                            <x-filament::icon icon="heroicon-m-arrow-up-right" class="iv-tile__go" />
                        </div>
                        <div>
                            <h3 class="iv-tile__title">{{ $card['title'] }}</h3>
                            <p class="iv-tile__desc">{{ $card['desc'] }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        <nav class="iv-dock" aria-label="Quick navigation">
            <div class="iv-dock__inner">
                @foreach ([
                    ['url' => \App\Filament\Pages\Dashboard::getUrl(), 'label' => 'Home', 'icon' => 'heroicon-o-home'],
                    ['url' => \App\Filament\Resources\ProductResource::getUrl(), 'label' => 'Products', 'icon' => 'heroicon-o-shopping-bag'],
                    ['url' => \App\Filament\Resources\InventorySaleResource::getUrl('create'), 'label' => 'POS', 'icon' => 'heroicon-o-qr-code', 'active' => true],
                    ['url' => \App\Filament\Resources\PurchaseOrderResource::getUrl(), 'label' => 'PO', 'icon' => 'heroicon-o-clipboard-document-check'],
                    ['url' => \App\Filament\Pages\AccountingHub::getUrl(), 'label' => 'COGS', 'icon' => 'heroicon-o-calculator'],
                ] as $link)
                    <a
                        href="{{ $link['url'] }}"
                        @class([
                            'iv-dock__link',
                            'iv-dock__link--active' => ! empty($link['active']),
                        ])
                    >
                        <x-filament::icon :icon="$link['icon']" />
                        <span>{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </nav>
    </div>
</x-filament-panels::page>
