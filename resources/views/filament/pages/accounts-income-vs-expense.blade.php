@php
    $report = $this->report;
    $pl = $report['pl'];
    $snap = $report['snap'];
@endphp

<x-filament-panels::page class="isp-accounts-page">
    <div class="space-y-5">
        <section class="isp-accounts-hero isp-accounts-hero--compact">
            <div class="isp-accounts-hero__main">
                <p class="isp-accounts-hero__eyebrow">Accounts</p>
                <h2 class="isp-accounts-hero__title">Income vs expense</h2>
                <p class="isp-accounts-hero__sub">Profit & loss summary with cashbook and collection breakdown.</p>
            </div>
        </section>

        <section class="isp-accounts-form-card isp-accounts-form-card--inline">
            {{ $this->form }}
        </section>

        <section class="isp-accounts-stats">
            <div class="isp-accounts-stat isp-accounts-stat--income">
                <span class="isp-accounts-stat__label">Income (GL)</span>
                <strong>{{ number_format($pl['income'], 2) }}</strong>
            </div>
            <div class="isp-accounts-stat isp-accounts-stat--expense">
                <span class="isp-accounts-stat__label">Expenses (GL)</span>
                <strong>{{ number_format($pl['expenses'], 2) }}</strong>
            </div>
            <div class="isp-accounts-stat isp-accounts-stat--primary">
                <span class="isp-accounts-stat__label">Net profit</span>
                <strong>{{ number_format($pl['net_profit'], 2) }}</strong>
            </div>
            <div class="isp-accounts-stat">
                <span class="isp-accounts-stat__label">Collections</span>
                <strong>{{ number_format($snap['collections'], 2) }}</strong>
            </div>
            <div class="isp-accounts-stat">
                <span class="isp-accounts-stat__label">Cash in</span>
                <strong>{{ number_format($snap['cashbook_in'], 2) }}</strong>
            </div>
            <div class="isp-accounts-stat">
                <span class="isp-accounts-stat__label">Cash out</span>
                <strong>{{ number_format($snap['cashbook_out'], 2) }}</strong>
            </div>
        </section>

        <section class="isp-accounts-bar">
            <div class="isp-accounts-bar__income" style="width: {{ $report['income_pct'] }}%"></div>
            <div class="isp-accounts-bar__expense" style="width: {{ 100 - $report['income_pct'] }}%"></div>
        </section>
    </div>
</x-filament-panels::page>
