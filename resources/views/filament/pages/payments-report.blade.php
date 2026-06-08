@php
    $summary = $this->summary;
    $isPrint = request()->boolean('print');
    $pageCount = $this->getTableRecords()->count();
@endphp

<x-filament-panels::page @class(['isp-bi-page--print' => $isPrint])>
    <div class="isp-bi-page">
        @unless ($isPrint)
            <x-isp.reports.page-header
                eyebrow="Collection analytics"
                title="Payments report"
                :subtitle="'Period: '.$this->periodLabel"
                score-label="Total amount"
                :score-value="number_format($summary['total_amount'], 0).' BDT'"
            >
                <span class="isp-bi-chip">{{ $this->gatewayFilterLabel }}</span>
                <span class="isp-bi-chip">{{ $this->walletFilterLabel }}</span>
            </x-isp.reports.page-header>

            <div class="isp-bi-toolbar">
                <a href="{{ \App\Filament\Pages\ReportsHub::getUrl() }}" class="isp-bi-back">← Intelligence center</a>
                <a href="{{ \App\Filament\Pages\AnalyticsReports::getUrl(['tab' => 'collection']) }}" class="isp-bi-back">Analytics view →</a>
            </div>

            <div class="isp-bi-filters isp-reports-filters">
                <div class="isp-reports-filters__field">
                    <label for="pay-from">From</label>
                    <input id="pay-from" type="date" wire:model.live="dateFrom" class="isp-reports-filters__input" />
                </div>
                <div class="isp-reports-filters__field">
                    <label for="pay-to">To</label>
                    <input id="pay-to" type="date" wire:model.live="dateTo" class="isp-reports-filters__input" />
                </div>
                <div class="isp-reports-filters__field">
                    <label for="pay-gateway">Method</label>
                    <select id="pay-gateway" wire:model.live="gatewayFilter" class="isp-reports-filters__input">
                        <option value="all">All methods</option>
                        <option value="bkash">bKash</option>
                        <option value="nagad">Nagad</option>
                        <option value="cash">Cash</option>
                        <option value="bank">Bank</option>
                    </select>
                </div>
                <div class="isp-reports-filters__field">
                    <label for="pay-wallet">Wallet</label>
                    <select id="pay-wallet" wire:model.live="walletFilter" class="isp-reports-filters__input">
                        <option value="all">All Wallets</option>
                        <option value="wallet">Wallet only</option>
                        <option value="invoice">Invoice payments</option>
                    </select>
                </div>
            </div>
        @else
            <header class="mb-4">
                <h1 class="text-xl font-bold">Payments Report</h1>
                <p class="text-sm text-gray-600">{{ $this->periodLabel }} · {{ $this->gatewayFilterLabel }}</p>
            </header>
        @endunless

        <div class="isp-bi-kpi-grid isp-bi-kpi-grid--4">
            <x-isp.reports.kpi-card label="Total amount" :value="number_format($summary['total_amount'], 2).' BDT'" tone="emerald" />
            <x-isp.reports.kpi-card label="Total discount" :value="number_format($summary['total_discount'], 2).' BDT'" tone="amber" />
            <x-isp.reports.kpi-card label="Total rows" :value="number_format($summary['total_rows'])" tone="violet" />
            <x-isp.reports.kpi-card label="Page rows" :value="number_format($pageCount)" :hint="number_format($summary['grouped_items']).' grouped items'" tone="sky" />
        </div>

        <section class="isp-bi-section">
            <div class="isp-bi-section__head">
                <div>
                    <h2 class="isp-bi-section__title">Payment rows</h2>
                    <p class="isp-bi-section__desc">{{ number_format($pageCount) }} of {{ number_format($summary['total_rows']) }} records on this page</p>
                </div>
            </div>
            <div class="isp-bi-section__body isp-bi-table-wrap">
                {{ $this->table }}
            </div>
        </section>
    </div>

    @if ($isPrint)
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</x-filament-panels::page>
