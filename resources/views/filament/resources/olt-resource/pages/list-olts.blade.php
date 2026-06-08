@php
    $stats = $this->getOltFleetStats();
    $dockLinks = $this->getOltDockLinks();
@endphp

{!! \App\Support\OltStyles::navigatedScript() !!}

<x-filament-panels::page class="isp-olt-list-page">
    <div class="olt-oc-pro">
        <header class="olt-list-hero">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest opacity-85">OLT fleet</p>
                <h1 class="olt-list-hero__title">OLT management</h1>
                <p class="olt-list-hero__sub">Chassis inventory, SNMP health, VPN tunnels, PON ports, and ONU sync — multi-vendor GPON.</p>
            </div>
            <a href="{{ \App\Filament\Resources\OltResource::getUrl('create') }}" class="olt-hub-btn olt-hub-btn--white" style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.5rem 0.9rem;border-radius:10px;background:#fff;color:#1e40af;font-weight:600;text-decoration:none;">
                <x-filament::icon icon="heroicon-m-plus" class="h-4 w-4" />
                Add OLT
            </a>
        </header>

        <div class="olt-oc-grid">
            <article class="olt-oc-kpi olt-oc-kpi--cyan">
                <span class="olt-oc-kpi__label">Total OLTs</span>
                <strong class="olt-oc-kpi__value">{{ number_format($stats['total']) }}</strong>
            </article>
            <article class="olt-oc-kpi olt-oc-kpi--emerald">
                <span class="olt-oc-kpi__label">Online</span>
                <strong class="olt-oc-kpi__value">{{ number_format($stats['online']) }}</strong>
            </article>
            <article class="olt-oc-kpi olt-oc-kpi--rose">
                <span class="olt-oc-kpi__label">Offline</span>
                <strong class="olt-oc-kpi__value">{{ number_format($stats['offline']) }}</strong>
            </article>
            <article class="olt-oc-kpi olt-oc-kpi--violet">
                <span class="olt-oc-kpi__label">ONUs</span>
                <strong class="olt-oc-kpi__value">{{ number_format($stats['onus']) }}</strong>
            </article>
        </div>

        <section class="olt-list-table-card">
            <div style="padding:0.75rem 1rem;border-bottom:1px solid var(--olt-border);font-weight:700;color:var(--olt-text);">
                Registered OLTs
            </div>
            {{ $this->table }}
        </section>

        <nav class="olt-dock olt-dock--mobile" aria-label="OLT quick nav">
            <div class="olt-dock__inner">
                @foreach ($dockLinks as $link)
                    <a href="{{ $link['url'] }}" @class(['olt-dock__link', 'olt-dock__link--active' => ! empty($link['active'])])>
                        <x-filament::icon :icon="$link['icon']" class="h-5 w-5" />
                        <span>{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </nav>
    </div>
</x-filament-panels::page>
