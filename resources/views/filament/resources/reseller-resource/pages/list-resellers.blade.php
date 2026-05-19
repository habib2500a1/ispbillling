@php
    $stats = $this->getResellerStats();
@endphp

<x-filament-panels::page class="isp-reseller-page">
    <div class="space-y-5">
        <section class="isp-reseller-hero">
            <div class="isp-reseller-hero__main">
                <p class="isp-reseller-hero__eyebrow">Resellers</p>
                <h2 class="isp-reseller-hero__title">All resellers</h2>
                <p class="isp-reseller-hero__sub">
                    Manage partners, franchises, commissions, territories, and wallet balances.
                </p>
            </div>
            <div class="isp-reseller-hero__stats">
                <div class="isp-reseller-stat">
                    <span class="isp-reseller-stat__label">Partners</span>
                    <strong>{{ number_format($stats['total']) }}</strong>
                </div>
                <div class="isp-reseller-stat isp-reseller-stat--primary">
                    <span class="isp-reseller-stat__label">Active</span>
                    <strong>{{ number_format($stats['active']) }}</strong>
                </div>
                <div class="isp-reseller-stat">
                    <span class="isp-reseller-stat__label">Wallet pool</span>
                    <strong>{{ number_format($stats['wallet_total'], 0) }}</strong>
                </div>
                <div class="isp-reseller-stat">
                    <span class="isp-reseller-stat__label">Pending comm.</span>
                    <strong>{{ number_format($stats['pending_commission'], 0) }}</strong>
                </div>
            </div>
        </section>

        <section class="isp-reseller-table-card">
            {{ $this->table }}
        </section>
    </div>
</x-filament-panels::page>
