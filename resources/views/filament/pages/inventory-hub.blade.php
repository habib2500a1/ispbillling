@php
    $m = $metrics ?? [];
    $fmt = fn ($n) => number_format((float) $n, 0);
@endphp

<script src="{{ asset('js/inventory-asset-intelligence.js') }}?v={{ @filemtime(public_path('js/inventory-asset-intelligence.js')) ?: 1 }}" defer data-cfasync="false"></script>

<x-filament-panels::page class="isp-inventory-hub-page">
    <div class="iv-pro iv-ai-pro">
        <header class="iv-hero iv-ai-hero">
            <div class="iv-hero__grid">
                <span class="iv-hero__badge">ISP Asset Intelligence</span>
                <h1 class="iv-hero__title">Inventory &amp; Asset Command</h1>
                <p class="iv-hero__sub">
                    Warehouse · procurement · CPE lifecycle · GIS-linked assets · QR scanning · technician accountability — integrated with billing, NOC &amp; fiber plant.
                </p>
                <div class="iv-hero__actions">
                    <a href="{{ \App\Filament\Resources\InventorySaleResource::getUrl('create') }}" class="iv-btn iv-btn--white">
                        <x-filament::icon icon="heroicon-m-qr-code" class="h-4 w-4" />
                        Scan &amp; sell (POS)
                    </a>
                    <a href="{{ \App\Filament\Resources\ProductResource::getUrl('create') }}" class="iv-btn iv-btn--glass">
                        <x-filament::icon icon="heroicon-m-plus" class="h-4 w-4" />
                        New SKU
                    </a>
                    <a href="{{ \App\Filament\Resources\DeviceResource::getUrl('create') }}" class="iv-btn iv-btn--glass">
                        <x-filament::icon icon="heroicon-m-wifi" class="h-4 w-4" />
                        Register CPE
                    </a>
                    <a href="{{ \App\Filament\Resources\WarehouseResource::getUrl() }}" class="iv-btn iv-btn--glass">
                        <x-filament::icon icon="heroicon-m-building-library" class="h-4 w-4" />
                        Warehouses
                    </a>
                </div>
            </div>
            <div class="iv-hero__highlight">
                <div class="iv-hero__highlight-card iv-ai-glass-card">
                    <span class="iv-hero__highlight-label">Stock value (cost)</span>
                    <strong class="iv-hero__highlight-value">{{ $fmt($m['stock_value'] ?? 0) }} BDT</strong>
                    <span class="iv-hero__highlight-hint">
                        {{ $fmt($m['stock_units'] ?? 0) }} units · {{ $m['product_count'] ?? 0 }} SKUs · {{ $m['warehouse_count'] ?? 0 }} warehouses
                    </span>
                </div>
            </div>
        </header>

        <section class="iv-ai-section">
            <div class="iv-section__head">
                <div>
                    <h2 class="iv-section__title">Asset dashboard</h2>
                    <p class="iv-section__sub">Fleet health · assignment · risk signals</p>
                </div>
            </div>
            <div class="iv-stats iv-stats--8">
                @foreach ($this->getAssetDashboardCards() as $kpi)
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
        </section>

        <section class="iv-ai-section">
            <div class="iv-section__head">
                <div>
                    <h2 class="iv-section__title">Asset lifecycle</h2>
                    <p class="iv-section__sub">Purchased → stored → assigned → installed → maintained → returned → retired</p>
                </div>
            </div>
            <div class="iv-lifecycle" data-iv-lifecycle>
                @foreach ($this->getLifecycleStages() as $index => $stage)
                    <article class="iv-lifecycle__stage" data-count="{{ $stage['count'] }}">
                        <div class="iv-lifecycle__dot">{{ $index + 1 }}</div>
                        <div class="iv-lifecycle__body">
                            <strong class="iv-lifecycle__label">{{ $stage['label'] }}</strong>
                            <span class="iv-lifecycle__count">{{ number_format($stage['count']) }}</span>
                            <span class="iv-lifecycle__desc">{{ $stage['desc'] }}</span>
                        </div>
                        @if ($index < count($this->getLifecycleStages()) - 1)
                            <div class="iv-lifecycle__arrow" aria-hidden="true">↓</div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <div class="iv-ai-grid-2">
            <section class="iv-ai-section">
                <div class="iv-section__head">
                    <div>
                        <h2 class="iv-section__title">Asset types</h2>
                        <p class="iv-section__sub">OLT · ONU · router · fiber · tools</p>
                    </div>
                </div>
                <div class="iv-type-grid">
                    @foreach ($this->getAssetTypes() as $type)
                        <a href="{{ \App\Filament\Resources\DeviceResource::getUrl() }}" class="iv-type-card">
                            <span class="iv-type-card__icon">
                                <x-filament::icon :icon="$type['icon']" class="h-5 w-5" />
                            </span>
                            <span class="iv-type-card__label">{{ $type['label'] }}</span>
                            <strong class="iv-type-card__count">{{ number_format($type['count']) }}</strong>
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="iv-ai-section">
                <div class="iv-section__head">
                    <div>
                        <h2 class="iv-section__title">Smart alerts</h2>
                        <p class="iv-section__sub">Low stock · failures · warranty · maintenance</p>
                    </div>
                </div>
                <div class="iv-alert-list">
                    @foreach ($this->getSmartAlerts() as $alert)
                        <a href="{{ $alert['url'] }}" @class(['iv-alert iv-alert--' . $alert['tone'], 'iv-alert--active' => $alert['count'] > 0])>
                            <span class="iv-alert__label">{{ $alert['label'] }}</span>
                            <strong class="iv-alert__count">{{ number_format($alert['count']) }}</strong>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>

        <section class="iv-ai-section">
            <div class="iv-section__head">
                <div>
                    <h2 class="iv-section__title">Operations analytics</h2>
                    <p class="iv-section__sub">Utilization · movement · purchases · revenue</p>
                </div>
            </div>
            <div class="iv-analytics">
                @foreach ($this->getAnalyticsCards() as $card)
                    <article class="iv-analytics__card">
                        <span class="iv-analytics__label">{{ $card['label'] }}</span>
                        <strong class="iv-analytics__value">{{ $card['value'] }}</strong>
                        <span class="iv-analytics__hint">{{ $card['hint'] }}</span>
                    </article>
                @endforeach
            </div>
            <div class="iv-stats iv-stats--4" style="margin-top: 0.75rem;">
                @foreach ($this->getKpiCards() as $kpi)
                    <a href="{{ $kpi['url'] }}" class="iv-stat iv-stat--{{ $kpi['tone'] }}">
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
        </section>

        <div class="iv-ai-grid-2">
            <section class="iv-ai-section">
                <div class="iv-section__head">
                    <div>
                        <h2 class="iv-section__title">GIS integration</h2>
                        <p class="iv-section__sub">Customer · ONU · OLT · fiber map</p>
                    </div>
                </div>
                <div class="iv-link-cards">
                    @foreach ($this->getGisIntegrationLinks() as $link)
                        <a href="{{ $link['url'] }}" class="iv-link-card">
                            <x-filament::icon :icon="$link['icon']" class="iv-link-card__icon" />
                            <div>
                                <strong>{{ $link['title'] }}</strong>
                                <span>{{ $link['desc'] }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="iv-ai-section">
                <div class="iv-section__head">
                    <div>
                        <h2 class="iv-section__title">QR &amp; barcode center</h2>
                        <p class="iv-section__sub">Labels · POS scan · mobile field ops</p>
                    </div>
                </div>
                <div class="iv-link-cards">
                    @foreach ($this->getQrBarcodeLinks() as $link)
                        <a href="{{ $link['url'] }}" class="iv-link-card">
                            <x-filament::icon :icon="$link['icon']" class="iv-link-card__icon" />
                            <div>
                                <strong>{{ $link['title'] }}</strong>
                                <span>{{ $link['desc'] }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>

        <section class="iv-ai-section">
            <div class="iv-section__head">
                <div>
                    <h2 class="iv-section__title">Modules &amp; workflows</h2>
                    <p class="iv-section__sub">Warehouse · procurement · technician tracking · accounting</p>
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
                    ['url' => \App\Filament\Resources\WarehouseResource::getUrl(), 'label' => 'WH', 'icon' => 'heroicon-o-building-library'],
                    ['url' => \App\Filament\Resources\InventorySaleResource::getUrl('create'), 'label' => 'Scan', 'icon' => 'heroicon-o-qr-code', 'active' => true],
                    ['url' => \App\Filament\Resources\DeviceResource::getUrl(), 'label' => 'CPE', 'icon' => 'heroicon-o-wifi'],
                    ['url' => \App\Filament\Pages\FiberPlantMap::getUrl(), 'label' => 'GIS', 'icon' => 'heroicon-o-map'],
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
