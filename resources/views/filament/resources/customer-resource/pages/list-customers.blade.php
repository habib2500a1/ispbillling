@php
    $stats = $this->getClientStats();
    $tabs = $this->getPresetTabs();
    $indexUrl = \App\Filament\Resources\CustomerResource::getUrl('index');
@endphp

<x-filament-panels::page class="isp-clients-page">
    <div class="space-y-5">
        <section class="isp-clients-hero">
            <div class="isp-clients-hero__main">
                <p class="isp-clients-hero__eyebrow">Client directory</p>
                <h2 class="isp-clients-hero__title">All clients</h2>
                <p class="isp-clients-hero__sub">
                    Manage subscribers, packages, balances, and connection status from one place.
                </p>
            </div>
            <div class="isp-clients-hero__stats">
                <div class="isp-clients-stat isp-clients-stat--primary">
                    <span class="isp-clients-stat__label">Total</span>
                    <strong>{{ number_format($stats['total'] ?? 0) }}</strong>
                </div>
                <div class="isp-clients-stat">
                    <span class="isp-clients-stat__label">Online</span>
                    <strong>{{ number_format($stats['online'] ?? 0) }}</strong>
                </div>
                <div class="isp-clients-stat">
                    <span class="isp-clients-stat__label">Active</span>
                    <strong>{{ number_format($stats['active'] ?? 0) }}</strong>
                </div>
                <div class="isp-clients-stat">
                    <span class="isp-clients-stat__label">Suspended</span>
                    <strong>{{ number_format($stats['suspended'] ?? 0) }}</strong>
                </div>
            </div>
        </section>

        <nav class="isp-clients-tabs" aria-label="Client filters">
            @foreach ($tabs as $tab)
                <a
                    href="{{ $indexUrl }}?preset={{ $tab['key'] }}"
                    @class([
                        'isp-clients-tab',
                        'isp-clients-tab--active' => $preset === $tab['key'],
                    ])
                >
                    {{ $tab['label'] }}
                    <span class="isp-clients-tab__count">{{ number_format($tab['count']) }}</span>
                </a>
            @endforeach
        </nav>

        <section class="isp-clients-table-card">
            <div class="isp-clients-table-card__head">
                <h3>Client directory</h3>
                <span class="isp-clients-table-card__meta">Portal column = direct login · row Actions = View, Edit, token</span>
            </div>
            {{ $this->table }}
        </section>
    </div>
</x-filament-panels::page>
