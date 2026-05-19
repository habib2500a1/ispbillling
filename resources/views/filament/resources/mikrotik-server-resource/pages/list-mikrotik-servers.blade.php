@php
    $stats = $this->getRouterStats();
@endphp

<x-filament-panels::page class="isp-network-page">
    <div class="space-y-5">
        <section class="isp-network-hero">
            <div class="isp-network-hero__main">
                <p class="isp-network-hero__eyebrow">Network</p>
                <h2 class="isp-network-hero__title">Routers list</h2>
                <p class="isp-network-hero__sub">
                    MikroTik RouterOS servers for PPPoE sync, live sessions, and subscriber import.
                </p>
            </div>
            <div class="isp-network-hero__stats">
                <div class="isp-network-stat">
                    <span class="isp-network-stat__label">Routers</span>
                    <strong>{{ number_format($stats['total']) }}</strong>
                </div>
                <div class="isp-network-stat isp-network-stat--primary">
                    <span class="isp-network-stat__label">Online</span>
                    <strong>{{ number_format($stats['online']) }}</strong>
                </div>
                <div class="isp-network-stat">
                    <span class="isp-network-stat__label">Enabled</span>
                    <strong>{{ number_format($stats['enabled']) }}</strong>
                </div>
                <div class="isp-network-stat">
                    <span class="isp-network-stat__label">Subscribers</span>
                    <strong>{{ number_format($stats['subscribers']) }}</strong>
                </div>
            </div>
        </section>

        <section class="isp-network-table-card">
            <div class="isp-network-table-card__head">
                <h3>Registered routers</h3>
            </div>
            {{ $this->table }}
        </section>
    </div>
</x-filament-panels::page>
