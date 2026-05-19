@php
    $stats = $this->walletStats;
@endphp

<x-filament-panels::page class="isp-reseller-page">
    <div class="space-y-5">
        <section class="isp-reseller-hero">
            <div class="isp-reseller-hero__main">
                <p class="isp-reseller-hero__eyebrow">Resellers</p>
                <h2 class="isp-reseller-hero__title">Wallet</h2>
                <p class="isp-reseller-hero__sub">
                    Partner prepaid balances — top up, transfer, and monitor negative wallets.
                </p>
            </div>
            <div class="isp-reseller-hero__stats">
                <div class="isp-reseller-stat isp-reseller-stat--primary">
                    <span class="isp-reseller-stat__label">Total balance</span>
                    <strong>{{ number_format($stats['total'], 2) }}</strong>
                </div>
                <div class="isp-reseller-stat">
                    <span class="isp-reseller-stat__label">Partners</span>
                    <strong>{{ number_format($stats['partners']) }}</strong>
                </div>
                @if ($stats['negative'] > 0)
                    <div class="isp-reseller-stat isp-reseller-stat--danger">
                        <span class="isp-reseller-stat__label">Negative</span>
                        <strong>{{ number_format($stats['negative']) }}</strong>
                    </div>
                @endif
            </div>
        </section>

        <section class="isp-reseller-table-card">
            {{ $this->table }}
        </section>
    </div>
</x-filament-panels::page>
