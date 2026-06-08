@php
    $hubUrl = $this->inventoryHubUrl();
    $warrantyAlerts = $this->getWarrantyAlertCount();
@endphp

<script src="{{ asset('js/inventory-asset-intelligence.js') }}?v={{ @filemtime(public_path('js/inventory-asset-intelligence.js')) ?: 1 }}" defer data-cfasync="false"></script>

<x-filament-panels::page class="isp-inventory-hub-page isp-inventory-report-page">
    <div class="iv-pro iv-list-shell">
        <header class="iv-form-hero iv-list-hero">
            <a href="{{ $hubUrl }}" class="iv-form-hero__back">
                <x-filament::icon icon="heroicon-m-arrow-left" class="h-4 w-4" />
                Asset intelligence center
            </a>
            <div class="iv-list-hero__row">
                <div>
                    <p class="iv-report-eyebrow">Inventory intelligence</p>
                    <h1 class="iv-form-hero__title">{{ $this->getReportTitle() }}</h1>
                    @if (filled($this->getReportSubtitle()))
                        <p class="iv-form-hero__sub">{{ $this->getReportSubtitle() }}</p>
                    @endif
                </div>
            </div>
            @if (method_exists($this, 'getReportFiltersView'))
                <div class="iv-report-filters">
                    {{ $this->getReportFiltersView() }}
                </div>
            @endif
        </header>

        @if ($warrantyAlerts > 0 && str_contains(strtolower($this->getReportTitle()), 'warranty'))
            <div class="iv-warranty-banner" role="status">
                <x-filament::icon icon="heroicon-o-shield-exclamation" class="h-5 w-5" />
                <span><strong>{{ $warrantyAlerts }}</strong> device warranties expire within 30 days.</span>
                <a href="{{ \App\Filament\Pages\InventoryWarrantyManagement::getUrl() }}">Review →</a>
            </div>
        @endif

        @if (! empty($reportStats))
            <div class="iv-list-stats">
                @foreach ($reportStats as $stat)
                    <article @class(['iv-list-stat', 'iv-list-stat--' . ($stat['tone'] ?? 'orange')])>
                        <span class="iv-list-stat__label">{{ $stat['label'] }}</span>
                        <strong class="iv-list-stat__value">{{ $stat['value'] }}</strong>
                    </article>
                @endforeach
            </div>
        @endif

        <div class="iv-list-table-card">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
