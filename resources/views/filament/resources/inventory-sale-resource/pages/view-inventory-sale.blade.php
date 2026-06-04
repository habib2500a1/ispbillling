@php
    $record = $this->getRecord();
    $printUrl = route('inventory-sales.receipt', $record).'?print=1';
    $pdfUrl = route('inventory-sales.receipt.pdf', $record);
@endphp

<x-filament-panels::page
    class="isp-inventory-hub-page fi-resource-view-record-page fi-resource-inventory-sales"
>
    <div class="iv-pro iv-list-shell">
        @if (request()->boolean('print'))
            <div class="iv-receipt-banner no-print">
                <p>Receipt opened for printing.</p>
                <a href="{{ $printUrl }}" target="_blank" rel="noopener" class="iv-btn iv-btn--white iv-btn--sm">Open receipt again</a>
            </div>
        @endif

        <header class="iv-form-hero iv-list-hero">
            <a href="{{ \App\Filament\Resources\InventorySaleResource::getUrl() }}" class="iv-form-hero__back">
                <x-filament::icon icon="heroicon-m-arrow-left" class="h-4 w-4" />
                Sales
            </a>
            <div class="iv-list-hero__row">
                <div>
                    <h1 class="iv-form-hero__title">{{ $record->sale_number }}</h1>
                    <p class="iv-form-hero__sub">
                        {{ $record->sold_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}
                        · {{ number_format((float) $record->total, 2) }} BDT
                        · Profit {{ number_format((float) $record->gross_profit, 2) }} BDT
                    </p>
                </div>
                <div class="iv-list-hero__cta-group">
                    <a href="{{ $printUrl }}" target="_blank" rel="noopener" class="iv-btn iv-btn--white iv-list-hero__cta">
                        <x-filament::icon icon="heroicon-m-printer" class="h-4 w-4" />
                        Thermal
                    </a>
                    <a href="{{ $pdfUrl }}" target="_blank" rel="noopener" class="iv-btn iv-btn--glass iv-list-hero__cta">
                        <x-filament::icon icon="heroicon-m-document-arrow-down" class="h-4 w-4" />
                        PDF
                    </a>
                </div>
            </div>
        </header>

        <div class="iv-list-table-card">
            {{ $this->infolist }}
        </div>
    </div>

    @if (request()->boolean('print'))
        <script>
            window.open(@json($printUrl), '_blank');
        </script>
    @endif
</x-filament-panels::page>
