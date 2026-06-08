@php
    $payload = $this->getFaultPayload();
    $summary = $payload['summary'];
    $faults = $payload['faults'];
    $rca = $this->getRootCauses();
@endphp

{!! \App\Support\IspOsStyles::navigatedScript() !!}

<x-filament-panels::page>
    <div class="ios-pro space-y-5">
        <header class="ios-hero">
            <div>
                <p class="text-xs font-bold uppercase tracking-widest opacity-85">NOC</p>
                <h1 class="ios-hero__title">Fault management center</h1>
                <p class="ios-hero__sub">Active faults, signal alerts, offline devices, fiber cuts — severity-ranked.</p>
            </div>
        </header>

        <div class="ios-fault-grid">
            @foreach ([
                ['Active faults', $summary['active'] ?? 0, ''],
                ['Critical', $summary['critical'] ?? 0, 'critical'],
                ['Warnings', $summary['warning'] ?? 0, 'warning'],
                ['Offline devices', $summary['offline_devices'] ?? 0, 'critical'],
                ['Signal alerts', $summary['signal_alerts'] ?? 0, 'warning'],
            ] as [$label, $value, $tone])
                <article @class(['ios-fault-card', $tone ? 'ios-fault-card--'.$tone : ''])>
                    <span class="ios-kpi__label">{{ $label }}</span>
                    <strong class="ios-kpi__value">{{ number_format($value) }}</strong>
                </article>
            @endforeach
        </div>

        @if ($rca !== [])
            <section class="ios-panel">
                <div class="ios-panel__head">Probable root causes</div>
                <div style="padding:0.75rem;display:grid;gap:0.5rem;">
                    @foreach ($rca as $item)
                        <div class="ios-rca">{{ $item['message'] }}</div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="ios-panel">
            <div class="ios-panel__head">Active fault list</div>
            @forelse ($faults as $fault)
                <a href="{{ $fault['url'] ?? '#' }}" class="ios-fault-row" style="display:block;text-decoration:none;color:inherit;">
                    <div style="display:flex;justify-content:space-between;gap:0.5rem;">
                        <strong>{{ $fault['title'] }}</strong>
                        <span class="ios-rca__badge">{{ $fault['severity'] }}</span>
                    </div>
                    <p style="margin:0.25rem 0 0;font-size:0.82rem;color:var(--ios-muted);">{{ $fault['message'] }}</p>
                    <span style="font-size:0.72rem;color:var(--ios-muted);">{{ $fault['entity'] }} · {{ $fault['at'] }}</span>
                </a>
            @empty
                <p style="padding:1.5rem;text-align:center;color:var(--ios-muted);">No active faults — network healthy.</p>
            @endforelse
        </section>

        <div style="display:flex;flex-wrap:gap:0.5rem;">
            <a href="{{ \App\Filament\Pages\FiberPlantMap::getUrl() }}" class="ios-module-card" style="flex:1;min-width:10rem;">GIS fault map →</a>
            <a href="{{ \App\Filament\Pages\OpticalMonitoringHub::getUrl(['tab' => 'alerts']) }}" class="ios-module-card" style="flex:1;min-width:10rem;">Optical alerts →</a>
            <a href="{{ \App\Filament\Pages\NocWall::getUrl() }}" class="ios-module-card" style="flex:1;min-width:10rem;">NOC wall →</a>
        </div>
    </div>
</x-filament-panels::page>
