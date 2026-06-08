@php
    $hubUrl = \App\Filament\Pages\InventoryHub::getUrl();
    $createUrl = \App\Filament\Resources\InventorySaleResource::getUrl('create');
@endphp

<x-filament-panels::page
    class="isp-inventory-hub-page fi-resource-list-records-page fi-resource-inventory-sales"
>
    <div class="iv-pro iv-list-shell">
        <header class="iv-form-hero iv-list-hero">
            <a href="{{ $hubUrl }}" class="iv-form-hero__back">
                <x-filament::icon icon="heroicon-m-arrow-left" class="h-4 w-4" />
                Inventory center
            </a>
            <div class="iv-list-hero__row">
                <div>
                    <h1 class="iv-form-hero__title">Retail sales</h1>
                    <p class="iv-form-hero__sub">POS history · profit per sale · payment method.</p>
                </div>
                <a href="{{ $createUrl }}" class="iv-btn iv-btn--white iv-list-hero__cta">
                    <x-filament::icon icon="heroicon-m-qr-code" class="h-4 w-4" />
                    New sale
                </a>
            </div>
        </header>

        @php
            $m = $inventorySummary ?? [];
            $saleStats = [
                ['label' => 'Month sales', 'value' => number_format((float) ($m['month_sales'] ?? 0)).' BDT', 'tone' => 'teal'],
                ['label' => 'Month profit', 'value' => number_format((float) ($m['month_profit'] ?? 0)).' BDT', 'tone' => 'emerald'],
                ['label' => 'Month COGS', 'value' => number_format((float) ($m['month_cogs'] ?? 0)).' BDT', 'tone' => 'amber'],
                ['label' => 'Stock value', 'value' => number_format((float) ($m['stock_value'] ?? 0)).' BDT', 'tone' => 'orange'],
            ];
        @endphp
        <div class="iv-list-stats">
            @foreach ($saleStats as $stat)
                <article @class(['iv-list-stat', 'iv-list-stat--' . ($stat['tone'] ?? 'orange')])>
                    <span class="iv-list-stat__label">{{ $stat['label'] }}</span>
                    <strong class="iv-list-stat__value">{{ $stat['value'] }}</strong>
                </article>
            @endforeach
        </div>

        <div class="iv-list-table-card">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
