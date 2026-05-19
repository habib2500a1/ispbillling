@php
    $summary = $this->walletSummary;
@endphp

<x-filament-panels::page class="isp-accounts-page">
    <div class="space-y-5">
        <section class="isp-accounts-hero isp-accounts-hero--compact">
            <div class="isp-accounts-hero__main">
                <p class="isp-accounts-hero__eyebrow">Accounts</p>
                <h2 class="isp-accounts-hero__title">Wallets</h2>
                <p class="isp-accounts-hero__sub">Company cashbook, bank accounts, collector float, and reseller prepaid balances.</p>
            </div>
        </section>

        <section class="isp-accounts-stats">
            <div class="isp-accounts-stat isp-accounts-stat--primary">
                <span class="isp-accounts-stat__label">Cashbook</span>
                <strong>{{ number_format($summary['cashbook'], 2) }}</strong>
            </div>
            <div class="isp-accounts-stat">
                <span class="isp-accounts-stat__label">Banks</span>
                <strong>{{ number_format($summary['banks'], 2) }}</strong>
            </div>
            <div class="isp-accounts-stat">
                <span class="isp-accounts-stat__label">Collectors</span>
                <strong>{{ number_format($summary['collectors'], 2) }}</strong>
            </div>
            <div class="isp-accounts-stat">
                <span class="isp-accounts-stat__label">Resellers</span>
                <strong>{{ number_format($summary['resellers'], 2) }}</strong>
            </div>
        </section>

        <section class="isp-accounts-table-card">
            {{ $this->table }}
        </section>
    </div>
</x-filament-panels::page>
