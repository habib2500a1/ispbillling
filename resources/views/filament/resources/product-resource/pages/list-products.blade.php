@php
    $s = $inventorySummary ?? [];
    $fmt = fn ($n) => number_format((float) $n, 0);
    $hubUrl = \App\Filament\Pages\InventoryHub::getUrl();
    $createUrl = \App\Filament\Resources\ProductResource::getUrl('create');
    $shopUrl = route('shop.index');
@endphp

<x-filament-panels::page
    class="isp-inventory-hub-page fi-resource-list-records-page fi-resource-products"
>
    <div class="iv-pro iv-list-shell">
        <header class="iv-form-hero iv-list-hero">
            <a href="{{ $hubUrl }}" class="iv-form-hero__back">
                <x-filament::icon icon="heroicon-m-arrow-left" class="h-4 w-4" />
                Inventory center
            </a>
            <div class="iv-list-hero__row">
                <div>
                    <h1 class="iv-form-hero__title">Products</h1>
                    <p class="iv-form-hero__sub">SKU · barcode · photos · warehouse stock · public shop.</p>
                </div>
                <a href="{{ $createUrl }}" class="iv-btn iv-btn--white iv-list-hero__cta">
                    <x-filament::icon icon="heroicon-m-plus" class="h-4 w-4" />
                    New product
                </a>
            </div>
            <div class="iv-form-hero__links">
                <a href="{{ \App\Filament\Resources\InventorySaleResource::getUrl('create') }}" class="iv-form-hero__link">POS sale</a>
                <a href="{{ $shopUrl }}" class="iv-form-hero__link" target="_blank" rel="noopener">Public shop</a>
            </div>
        </header>

        <div class="iv-list-stats">
            <article class="iv-list-stat iv-list-stat--teal">
                <span class="iv-list-stat__label">Active products</span>
                <strong class="iv-list-stat__value">{{ number_format((int) ($s['product_count'] ?? 0)) }}</strong>
            </article>
            <article class="iv-list-stat iv-list-stat--amber">
                <span class="iv-list-stat__label">Low stock</span>
                <strong class="iv-list-stat__value">{{ number_format((int) ($s['low_stock_count'] ?? 0)) }}</strong>
            </article>
            <article class="iv-list-stat iv-list-stat--sky">
                <span class="iv-list-stat__label">On shop</span>
                <strong class="iv-list-stat__value">{{ number_format((int) ($s['shop_products'] ?? 0)) }}</strong>
            </article>
            <article class="iv-list-stat iv-list-stat--orange">
                <span class="iv-list-stat__label">Stock value</span>
                <strong class="iv-list-stat__value">{{ $fmt($s['stock_value'] ?? 0) }} BDT</strong>
            </article>
        </div>

        <div class="iv-list-table-card">
            <x-filament-panels::resources.tabs />

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE, scopes: $this->getRenderHookScopes()) }}

            {{ $this->table }}

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER, scopes: $this->getRenderHookScopes()) }}
        </div>
    </div>
</x-filament-panels::page>
