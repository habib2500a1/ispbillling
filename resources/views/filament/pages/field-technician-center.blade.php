@php
    $stats = $this->getFieldStats();
    $visits = $this->getAssignedVisits();
@endphp

{!! \App\Support\IspOsStyles::navigatedScript() !!}

<x-filament-panels::page>
    <div class="ios-pro space-y-5">
        <header class="ios-hero">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest opacity-85">Field operations</p>
                <h1 class="ios-hero__title">Technician center</h1>
                <p class="ios-hero__sub">Assigned tasks, nearby customers, fiber paths, ONU/OLT lookup — mobile-first.</p>
            </div>
        </header>

        <div class="ios-kpi-grid">
            <article class="ios-kpi ios-kpi--amber">
                <span class="ios-kpi__label">Open tickets</span>
                <strong class="ios-kpi__value">{{ number_format($stats['open']) }}</strong>
            </article>
            <article class="ios-kpi ios-kpi--rose">
                <span class="ios-kpi__label">Urgent</span>
                <strong class="ios-kpi__value">{{ number_format($stats['urgent']) }}</strong>
            </article>
            <article class="ios-kpi ios-kpi--cyan">
                <span class="ios-kpi__label">Visits today</span>
                <strong class="ios-kpi__value">{{ number_format($stats['visits_today']) }}</strong>
            </article>
        </div>

        <section class="ios-panel">
            <div class="ios-panel__head">Assigned field visits</div>
            @forelse ($visits as $visit)
                <a href="{{ $visit['url'] }}" class="ios-field-card" style="display:block;margin:0.5rem 1rem;text-decoration:none;color:inherit;">
                    <div style="display:flex;justify-content:space-between;gap:0.5rem;">
                        <strong>{{ $visit['customer'] }} ({{ $visit['code'] }})</strong>
                        <span class="ios-rca__badge">{{ $visit['priority'] }}</span>
                    </div>
                    <p style="margin:0.25rem 0;font-size:0.82rem;">Ticket {{ $visit['ticket'] }} — {{ $visit['subject'] }}</p>
                    <span style="font-size:0.72rem;color:var(--ios-muted);">{{ $visit['scheduled'] }} · {{ $visit['status'] }}</span>
                </a>
            @empty
                <p style="padding:1.5rem;text-align:center;color:var(--ios-muted);">No scheduled field visits.</p>
            @endforelse
        </section>

        <div class="ios-modules">
            <a href="{{ \App\Filament\Pages\FiberPlantMap::getUrl() }}" class="ios-module-card"><div class="ios-module-card__title">GIS map</div><div class="ios-module-card__desc">Splitters, routes, customers</div></a>
            <a href="{{ \App\Filament\Pages\OpticalMonitoringHub::getUrl() }}" class="ios-module-card"><div class="ios-module-card__title">ONU lookup</div><div class="ios-module-card__desc">Signal & registration</div></a>
            <a href="{{ \App\Filament\Pages\OltHub::getUrl() }}" class="ios-module-card"><div class="ios-module-card__title">OLT lookup</div><div class="ios-module-card__desc">PON & chassis health</div></a>
            <a href="{{ \App\Filament\Pages\SupportHub::getUrl() }}" class="ios-module-card"><div class="ios-module-card__title">Tickets</div><div class="ios-module-card__desc">Support desk</div></a>
        </div>

        <nav class="ios-field-dock" aria-label="Mobile quick nav">
            <a href="{{ \App\Filament\Pages\FiberPlantMap::getUrl() }}" style="font-size:0.7rem;text-align:center;color:var(--ios-text);text-decoration:none;">Map</a>
            <a href="{{ \App\Filament\Pages\FaultManagementHub::getUrl() }}" style="font-size:0.7rem;text-align:center;color:var(--ios-text);text-decoration:none;">Faults</a>
            <a href="{{ \App\Filament\Pages\SupportHub::getUrl() }}" style="font-size:0.7rem;text-align:center;color:var(--ios-text);text-decoration:none;">Tickets</a>
            <a href="{{ \App\Filament\Pages\IspOsHub::getUrl() }}" style="font-size:0.7rem;text-align:center;color:var(--ios-text);text-decoration:none;">ISP OS</a>
        </nav>
    </div>
</x-filament-panels::page>
