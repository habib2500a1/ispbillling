@php
    $summary = $this->summary;
    $isPrint = request()->boolean('print');
    $pageCount = $this->getTableRecords()->count();
@endphp

<x-filament-panels::page @class(['isp-reports-page', 'isp-reports-page--print' => $isPrint])>
    <div class="space-y-5">
        <section class="isp-reports-hero">
            <div class="isp-reports-hero__main">
                <p class="isp-reports-hero__eyebrow">Reports</p>
                <h2 class="isp-reports-hero__title">Payments Report</h2>
                <span class="isp-reports-filter-badge">{{ $this->walletFilterLabel }}</span>
            </div>
            @unless ($isPrint)
                <div class="isp-reports-filters">
                    <div class="isp-reports-filters__field">
                        <label for="pay-from">From</label>
                        <input id="pay-from" type="date" wire:model.live="dateFrom" class="isp-reports-filters__input" />
                    </div>
                    <div class="isp-reports-filters__field">
                        <label for="pay-to">To</label>
                        <input id="pay-to" type="date" wire:model.live="dateTo" class="isp-reports-filters__input" />
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
            @endunless
        </section>

        <section class="isp-reports-stats">
            <div class="isp-reports-stat isp-reports-stat--primary">
                <span class="isp-reports-stat__label">Total Amount</span>
                <strong>{{ number_format($summary['total_amount'], 2) }}</strong>
            </div>
            <div class="isp-reports-stat isp-reports-stat--primary">
                <span class="isp-reports-stat__label">Total Discount</span>
                <strong>{{ number_format($summary['total_discount'], 2) }}</strong>
            </div>
            <div class="isp-reports-stat">
                <span class="isp-reports-stat__label">Total Rows</span>
                <strong>{{ number_format($summary['total_rows']) }}</strong>
            </div>
            <div class="isp-reports-stat">
                <span class="isp-reports-stat__label">Page Rows</span>
                <strong>{{ number_format($pageCount) }}</strong>
            </div>
            <div class="isp-reports-stat">
                <span class="isp-reports-stat__label">Grouped Items</span>
                <strong>{{ number_format($summary['grouped_items']) }}</strong>
            </div>
            <p class="isp-reports-period">Period: {{ $this->periodLabel }}</p>
        </section>

        <section class="isp-reports-table-card">
            <div class="isp-reports-table-card__head">
                <h3>Payment Rows</h3>
                <span>{{ number_format($pageCount) }} of {{ number_format($summary['total_rows']) }} records on this page</span>
            </div>
            {{ $this->table }}
        </section>
    </div>

    @if ($isPrint)
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    @endif
</x-filament-panels::page>
