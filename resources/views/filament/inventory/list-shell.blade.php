@php
    $hubUrl = \App\Filament\Pages\InventoryHub::getUrl();
@endphp

<script src="{{ asset('js/inventory-asset-intelligence.js') }}?v={{ @filemtime(public_path('js/inventory-asset-intelligence.js')) ?: 1 }}" defer data-cfasync="false"></script>

<x-filament-panels::page @class(['isp-inventory-hub-page', 'fi-resource-list-records-page', 'isp-inventory-list-page'])>
    <div class="iv-pro iv-list-shell">
        <header class="iv-form-hero iv-list-hero">
            <a href="{{ $hubUrl }}" class="iv-form-hero__back">
                <x-filament::icon icon="heroicon-m-arrow-left" class="h-4 w-4" />
                Asset intelligence center
            </a>
            <div class="iv-list-hero__row">
                <div>
                    <h1 class="iv-form-hero__title">{{ $inventoryListTitle }}</h1>
                    <p class="iv-form-hero__sub">{{ $inventoryListSubtitle }}</p>
                </div>
                @if ($inventoryListCreateUrl)
                    <a href="{{ $inventoryListCreateUrl }}" class="iv-btn iv-btn--white iv-list-hero__cta">
                        <x-filament::icon icon="heroicon-m-plus" class="h-4 w-4" />
                        {{ $inventoryListCreateLabel ?? 'Create' }}
                    </a>
                @endif
            </div>
            @if (! empty($inventoryListLinks))
                <div class="iv-form-hero__links">
                    @foreach ($inventoryListLinks as $link)
                        <a href="{{ $link['url'] }}" class="iv-form-hero__link">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            @endif
        </header>

        @if (! empty($inventoryListStats))
            <div class="iv-list-stats">
                @foreach ($inventoryListStats as $stat)
                    <article @class(['iv-list-stat', 'iv-list-stat--' . ($stat['tone'] ?? 'orange')])>
                        <span class="iv-list-stat__label">{{ $stat['label'] }}</span>
                        <strong class="iv-list-stat__value">{{ $stat['value'] }}</strong>
                    </article>
                @endforeach
            </div>
        @endif

        <div class="iv-list-table-card">
            <x-filament-panels::resources.tabs />

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE, scopes: $this->getRenderHookScopes()) }}

            {{ $this->table }}

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER, scopes: $this->getRenderHookScopes()) }}
        </div>
    </div>
</x-filament-panels::page>
