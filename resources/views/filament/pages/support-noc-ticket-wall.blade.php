@php
    $s = $this->getSnapshot();
    $oltRows = $s['olt_complaints'] ?? [];
    $incidents = $s['root_incidents'] ?? [];
    $mapPoints = $s['map_points'] ?? [];
@endphp

<x-filament-panels::page>
    <div class="sp-noc-wall" wire:poll.visible.60s="refreshSnapshot">
        <header class="sp-noc-wall__hero">
            <div>
                <p class="sp-noc-wall__eyebrow">Enterprise support · live desk</p>
                <h1 class="sp-noc-wall__title">Ticket NOC wall</h1>
                <p class="sp-noc-wall__lead">OLT-wise complaint clusters, root incidents, SLA pressure, and subscriber map.</p>
            </div>
            <button type="button" wire:click="refreshSnapshot" class="sp-noc-wall__refresh">Refresh</button>
        </header>

        <div class="sp-noc-wall__kpis">
            <article class="sp-noc-kpi sp-noc-kpi--open">
                <span class="sp-noc-kpi__label">Open</span>
                <strong class="sp-noc-kpi__value">{{ number_format($s['open'] ?? 0) }}</strong>
            </article>
            <article class="sp-noc-kpi sp-noc-kpi--critical">
                <span class="sp-noc-kpi__label">Critical</span>
                <strong class="sp-noc-kpi__value">{{ number_format($s['critical'] ?? 0) }}</strong>
            </article>
            <article class="sp-noc-kpi sp-noc-kpi--breach">
                <span class="sp-noc-kpi__label">SLA breach</span>
                <strong class="sp-noc-kpi__value">{{ number_format($s['sla_breach'] ?? 0) }}</strong>
            </article>
            <article class="sp-noc-kpi sp-noc-kpi--risk">
                <span class="sp-noc-kpi__label">First response risk</span>
                <strong class="sp-noc-kpi__value">{{ number_format($s['first_response_risk'] ?? 0) }}</strong>
            </article>
            <article class="sp-noc-kpi sp-noc-kpi--unassigned">
                <span class="sp-noc-kpi__label">Unassigned</span>
                <strong class="sp-noc-kpi__value">{{ number_format($s['unassigned'] ?? 0) }}</strong>
            </article>
        </div>

        <div class="sp-noc-wall__grid">
            <section class="sp-noc-panel">
                <h2 class="sp-noc-panel__title">OLT complaint heat</h2>
                <p class="sp-noc-panel__meta">Open tickets grouped by OLT — mass outage clusters surface here first.</p>
                @if ($oltRows === [])
                    <p class="sp-noc-empty">No OLT-linked open tickets.</p>
                @else
                    <div class="sp-noc-olt-list">
                        @foreach ($oltRows as $row)
                            <div class="sp-noc-olt-row">
                                <div>
                                    <strong>{{ $row['olt_name'] }}</strong>
                                    <span class="sp-noc-olt-row__id">OLT #{{ $row['olt_id'] }}</span>
                                </div>
                                <span class="sp-noc-olt-row__count">{{ $row['open_tickets'] }} open</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="sp-noc-panel">
                <h2 class="sp-noc-panel__title">Root incidents</h2>
                <p class="sp-noc-panel__meta">Auto-merged mass outage groups with child tickets.</p>
                @if ($incidents === [])
                    <p class="sp-noc-empty">No active root incidents.</p>
                @else
                    <div class="sp-noc-incident-list">
                        @foreach ($incidents as $incident)
                            <article class="sp-noc-incident">
                                <div class="sp-noc-incident__head">
                                    <strong>{{ $incident['number'] }}</strong>
                                    <span>{{ $incident['detected_at'] }}</span>
                                </div>
                                <p class="sp-noc-incident__title">{{ $incident['title'] }}</p>
                                <p class="sp-noc-incident__meta">
                                    {{ $incident['olt'] }} · {{ $incident['ticket_count'] }} tickets
                                    @if ($incident['primary_ticket'])
                                        · Primary {{ $incident['primary_ticket'] }}
                                    @endif
                                </p>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <section class="sp-noc-panel sp-noc-panel--map">
            <h2 class="sp-noc-panel__title">Complaint map</h2>
            <p class="sp-noc-panel__meta">Open tickets with GPS on subscriber profile ({{ count($mapPoints) }} plotted).</p>
            @if ($mapPoints === [])
                <p class="sp-noc-empty">No GPS-tagged open tickets — add gps_lat / gps_lng on subscriber meta.</p>
            @else
                <div id="sp-support-noc-map" class="sp-noc-map" wire:ignore></div>
            @endif
        </section>
    </div>

    @if ($mapPoints !== [])
        @push('scripts')
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const el = document.getElementById('sp-support-noc-map');
                    if (!el || typeof L === 'undefined') return;
                    const points = @json($mapPoints);
                    if (!points.length) return;
                    const map = L.map(el).setView([points[0].lat, points[0].lng], 12);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 18,
                        attribution: '&copy; OpenStreetMap',
                    }).addTo(map);
                    points.forEach(function (p) {
                        L.circleMarker([p.lat, p.lng], {
                            radius: p.priority === 'critical' ? 9 : 6,
                            color: p.priority === 'critical' ? '#dc2626' : '#2563eb',
                            fillOpacity: 0.85,
                        }).bindPopup(p.label).addTo(map);
                    });
                    setTimeout(function () { map.invalidateSize(); }, 300);
                });
            </script>
        @endpush
    @endif

    <style>
        .sp-noc-wall { display: flex; flex-direction: column; gap: 1.25rem; }
        .sp-noc-wall__hero { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; flex-wrap: wrap; }
        .sp-noc-wall__eyebrow { font-size: 0.72rem; letter-spacing: 0.08em; text-transform: uppercase; color: #64748b; margin: 0 0 0.35rem; }
        .sp-noc-wall__title { font-size: 1.65rem; font-weight: 800; margin: 0; color: #0f172a; }
        .sp-noc-wall__lead { margin: 0.35rem 0 0; color: #475569; max-width: 42rem; }
        .sp-noc-wall__refresh { border: 1px solid #cbd5e1; background: #fff; border-radius: 0.65rem; padding: 0.45rem 0.85rem; font-size: 0.85rem; cursor: pointer; }
        .sp-noc-wall__kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: 0.75rem; }
        .sp-noc-kpi { background: #fff; border: 1px solid #e2e8f0; border-radius: 0.85rem; padding: 0.85rem 1rem; }
        .sp-noc-kpi__label { display: block; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; }
        .sp-noc-kpi__value { font-size: 1.45rem; line-height: 1.2; color: #0f172a; }
        .sp-noc-wall__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        @media (max-width: 960px) { .sp-noc-wall__grid { grid-template-columns: 1fr; } }
        .sp-noc-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 0.95rem; padding: 1rem 1.1rem; }
        .sp-noc-panel__title { margin: 0; font-size: 1.05rem; font-weight: 700; color: #0f172a; }
        .sp-noc-panel__meta { margin: 0.25rem 0 0.85rem; font-size: 0.82rem; color: #64748b; }
        .sp-noc-empty { margin: 0; color: #94a3b8; font-size: 0.9rem; }
        .sp-noc-olt-list, .sp-noc-incident-list { display: flex; flex-direction: column; gap: 0.55rem; max-height: 22rem; overflow: auto; }
        .sp-noc-olt-row, .sp-noc-incident { display: flex; justify-content: space-between; gap: 0.75rem; align-items: center; padding: 0.65rem 0.75rem; border-radius: 0.65rem; background: #f8fafc; border: 1px solid #eef2f7; }
        .sp-noc-olt-row__id, .sp-noc-incident__meta { font-size: 0.78rem; color: #64748b; }
        .sp-noc-olt-row__count { font-weight: 700; color: #dc2626; white-space: nowrap; }
        .sp-noc-incident { flex-direction: column; align-items: flex-start; }
        .sp-noc-incident__head { width: 100%; display: flex; justify-content: space-between; gap: 0.5rem; font-size: 0.78rem; color: #64748b; }
        .sp-noc-incident__title { margin: 0.15rem 0 0; font-size: 0.92rem; color: #0f172a; }
        .sp-noc-map { height: 22rem; border-radius: 0.75rem; border: 1px solid #e2e8f0; }
    </style>
</x-filament-panels::page>
