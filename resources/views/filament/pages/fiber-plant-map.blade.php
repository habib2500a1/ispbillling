@php
    $payload = $this->getMapPayload();
    $stats = $payload['stats'];
    $config = $payload['config'];
    $ops = $payload['ops'] ?? [];
    $kpis = $ops['kpis'] ?? [];
    $legend = $ops['status_legend'] ?? [];
    $olts = $ops['olts'] ?? [];
    $coordProblems = $ops['coordinate_problems'] ?? ['missing_gps' => [], 'missing_coords' => []];
@endphp

<x-filament-panels::page class="isp-fiber-plant-page">
    <link rel="stylesheet" href="{{ asset('css/fiber-plant-map.css') }}?v={{ @filemtime(public_path('css/fiber-plant-map.css')) ?: 1 }}">
    <link rel="stylesheet" href="{{ asset('css/gis-intelligence.css') }}?v={{ @filemtime(public_path('css/gis-intelligence.css')) ?: 1 }}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" crossorigin="">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" crossorigin="">
    <link rel="manifest" href="{{ asset('manifest-gis.json') }}">

    <section class="fpm-hero">
        <div>
            <p class="fpm-hero__eyebrow">ISP OS · Live network GIS</p>
            <h2 class="fpm-hero__title">Network operations map</h2>
            <p class="fpm-hero__sub">
                Customer GPS, fiber plant, ONU/RX meter, PPP online/offline — ek map e.
                Kon user kon MikroTik/OLT/PON theke down — red/green status diye dekha jabe.
            </p>
        </div>
        <div class="fpm-hero__stats">
            <div class="fpm-hero__stat fpm-hero__stat--online"><strong>{{ number_format($kpis['ppp_online'] ?? 0) }}</strong><span>PPP online</span></div>
            <div class="fpm-hero__stat fpm-hero__stat--offline"><strong>{{ number_format($kpis['ppp_offline'] ?? 0) }}</strong><span>PPP offline</span></div>
            <div class="fpm-hero__stat fpm-hero__stat--onu"><strong>{{ number_format($kpis['onu_online_fleet'] ?? 0) }}/{{ number_format($kpis['onu_total_fleet'] ?? 0) }}</strong><span>ONU online</span></div>
            <div class="fpm-hero__stat fpm-hero__stat--onu-down"><strong>{{ number_format($kpis['onu_offline'] ?? 0) }}</strong><span>ONU down</span></div>
            <div class="fpm-hero__stat fpm-hero__stat--weak"><strong>{{ number_format($kpis['weak_signal'] ?? 0) }}</strong><span>Weak RX</span></div>
            <div><strong>{{ number_format($stats['total_cable_m'] ?? 0, 0) }}m</strong><span>Fiber plant</span></div>
            <div><strong>{{ number_format($kpis['subscribers_on_map'] ?? 0) }}</strong><span>On map</span></div>
        </div>
    </section>

    <div class="fpm-ops-bar">
        <div class="fpm-search-wrap">
            <label class="fpm-search">
                <span class="sr-only">Search subscriber</span>
                <input type="search" id="fpm-search" placeholder="Search name, username, phone, ID…" autocomplete="off">
            </label>
            <div id="fpm-search-results" class="fpm-search-results" hidden></div>
        </div>
        <div class="fpm-filters" id="fpm-filters">
            <button type="button" class="fpm-filter fpm-filter--active" data-filter="all">All</button>
            <button type="button" class="fpm-filter" data-filter="online">Online</button>
            <button type="button" class="fpm-filter" data-filter="ppp_offline">PPP off</button>
            <button type="button" class="fpm-filter" data-filter="onu_offline">ONU down</button>
            <button type="button" class="fpm-filter" data-filter="weak">Weak RX</button>
        </div>
        <button
            type="button"
            class="fpm-btn fpm-btn--ghost"
            id="fpm-refresh-ops"
            wire:click="refreshLiveStatus"
            wire:loading.attr="disabled"
            wire:target="refreshLiveStatus"
        >
            <span wire:loading.remove wire:target="refreshLiveStatus">↻ Live status</span>
            <span wire:loading wire:target="refreshLiveStatus">Refreshing…</span>
        </button>
        @if (! empty($legend))
            <div class="fpm-status-legend">
                @foreach ($legend as $item)
                    <span class="fpm-status-legend__item" style="--dot: {{ $item['color'] }}">{{ $item['label'] }}</span>
                @endforeach
            </div>
        @endif
    </div>

    @if (! empty($olts))
        <div class="fpm-olt-strip">
            <span class="fpm-olt-strip__label">OLT fleet</span>
            @foreach ($olts as $olt)
                <a href="{{ $olt['url'] }}" class="fpm-olt-chip" title="{{ $olt['ip'] ?? '' }}">
                    <strong>{{ $olt['label'] }}</strong>
                    <span class="fpm-olt-chip__onu"><em>{{ $olt['onu_online'] }}</em> online · <strong>{{ $olt['onu_total'] }}</strong> ONU</span>
                </a>
            @endforeach
            <a href="{{ \App\Filament\Pages\OltHub::getUrl() }}" class="fpm-olt-strip__more">OLT center →</a>
        </div>
    @endif

    <div class="fpm-layout">
        <aside class="fpm-panel" id="fpm-panel">
            <section class="fpm-import-card">
                <div class="fpm-import-card__icon" aria-hidden="true">📥</div>
                <div class="fpm-import-card__body">
                    <h3 class="fpm-import-card__title">Map এ import করুন</h3>
                    <p class="fpm-import-card__text">POP box, OLT location, আর subscriber GPS pin — এক ক্লিকে map এ যোগ হবে।</p>
                    <ul class="fpm-import-card__list">
                        <li>POP / FAT box</li>
                        <li>OLT device</li>
                        <li>Customer GPS (meta)</li>
                    </ul>
                    <button
                        type="button"
                        class="fpm-btn fpm-btn--primary fpm-import-card__btn"
                        id="fpm-import"
                        wire:click="importInfrastructure"
                        wire:loading.attr="disabled"
                        wire:target="importInfrastructure"
                    >
                        <span wire:loading.remove wire:target="importInfrastructure">↻ Import to map</span>
                        <span wire:loading wire:target="importInfrastructure">Importing…</span>
                    </button>
                </div>
            </section>

            <div class="fpm-toolbar">
                <button type="button" class="fpm-tool fpm-tool--active" data-mode="view" title="Select / pan">Select</button>
                <button type="button" class="fpm-tool" data-mode="add_node" title="Click map to add node">+ Node</button>
                <button type="button" class="fpm-tool" data-mode="draw_cable" title="Click two nodes">Cable</button>
            </div>

            <div id="fpm-customer-detail" class="fpm-customer-detail" hidden></div>

            <details class="fpm-coord-panel" @if(count($coordProblems['missing_gps'] ?? []) > 0) open @endif>
                <summary>Coordinate problems ({{ count($coordProblems['missing_gps'] ?? []) + count($coordProblems['missing_coords'] ?? []) }})</summary>
                @if (empty($coordProblems['missing_gps']) && empty($coordProblems['missing_coords']))
                    <p class="fpm-coord-panel__ok">No coordinate problems — সব pin ঠিক আছে।</p>
                @else
                    @if (! empty($coordProblems['missing_gps']))
                        <p class="fpm-coord-panel__head">GPS missing (subscriber)</p>
                        <ul class="fpm-coord-list">
                            @foreach ($coordProblems['missing_gps'] as $row)
                                <li><a href="{{ $row['url'] }}">{{ $row['name'] }} <small>{{ $row['code'] }}</small></a></li>
                            @endforeach
                        </ul>
                    @endif
                    @if (! empty($coordProblems['missing_coords']))
                        <p class="fpm-coord-panel__head">Node without map pin</p>
                        <ul class="fpm-coord-list">
                            @foreach ($coordProblems['missing_coords'] as $row)
                                <li>{{ $row['name'] }} <small>{{ $row['type'] }}</small></li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </details>

            <div class="fpm-legend">
                <p class="fpm-legend__title">Cable colors (TIA-598)</p>
                <div class="fpm-legend__swatches">
                    @foreach ($config['cable_colors'] as $key => $color)
                        <span class="fpm-swatch" style="--swatch: {{ $color['hex'] }}" title="{{ $color['label'] }}"></span>
                    @endforeach
                </div>
            </div>

            <form id="fpm-node-form" class="fpm-form" hidden>
                <h3 class="fpm-form__title">Node</h3>
                <input type="hidden" name="id" value="">
                <label class="fpm-field">
                    <span>Name</span>
                    <input type="text" name="name" required>
                </label>
                <label class="fpm-field">
                    <span>Code</span>
                    <input type="text" name="code">
                </label>
                <label class="fpm-field">
                    <span>Type</span>
                    <select name="type">
                        @foreach ($config['node_types'] as $key => $type)
                            <option value="{{ $key }}">{{ $type['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="fpm-field">
                    <span>Lat / Lng</span>
                    <div class="fpm-field-row">
                        <input type="number" step="any" name="latitude" placeholder="Lat">
                        <input type="number" step="any" name="longitude" placeholder="Lng">
                    </div>
                </label>
                <label class="fpm-field fpm-field--splitter">
                    <span>Splitter ratio (1:N)</span>
                    <select name="splitter_ratio">
                        <option value="">—</option>
                        @foreach ($config['splitter_ratios'] as $ratio)
                            <option value="{{ $ratio }}">1:{{ $ratio }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="fpm-field fpm-field--splitter">
                    <span>Output direction</span>
                    <select name="splitter_direction">
                        <option value="">—</option>
                        @foreach ($config['directions'] as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="fpm-field fpm-field--pon">
                    <span>PON name / port</span>
                    <input type="text" name="pon_label" placeholder="e.g. GPON0/1/3 — manual; auto from ONU">
                </label>
                <label class="fpm-field fpm-field--pon">
                    <span>OLT label (optional)</span>
                    <input type="text" name="olt_label" placeholder="OLT name for this splitter/POP">
                </label>
                <label class="fpm-field">
                    <span>Address / location note</span>
                    <input type="text" name="address">
                </label>
                <label class="fpm-field">
                    <span>Notes</span>
                    <textarea name="notes" rows="2"></textarea>
                </label>
                <div class="fpm-form__actions">
                    <button type="submit" class="fpm-btn fpm-btn--primary">Save node</button>
                    <button type="button" class="fpm-btn fpm-btn--danger" id="fpm-delete-node" hidden>Delete</button>
                </div>
            </form>

            <form id="fpm-edge-form" class="fpm-form" hidden>
                <h3 class="fpm-form__title">Cable segment</h3>
                <input type="hidden" name="id" value="">
                <input type="hidden" name="from_node_id" value="">
                <input type="hidden" name="to_node_id" value="">
                <p class="fpm-form__hint" id="fpm-edge-endpoints"></p>
                <label class="fpm-field">
                    <span>Cable type</span>
                    <select name="cable_type">
                        @foreach ($config['cable_types'] as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="fpm-field">
                    <span>Length (meters)</span>
                    <input type="number" step="0.01" min="0" name="length_m" required>
                </label>
                <label class="fpm-field">
                    <span>Fiber jacket color</span>
                    <select name="cable_color">
                        @foreach ($config['cable_colors'] as $key => $color)
                            <option value="{{ $key }}">{{ $color['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="fpm-field">
                    <span>Tube color (optional)</span>
                    <select name="tube_color">
                        <option value="">—</option>
                        @foreach ($config['cable_colors'] as $key => $color)
                            <option value="{{ $key }}">{{ $color['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="fpm-field">
                    <span>Direction from parent</span>
                    <select name="direction_label">
                        <option value="">—</option>
                        @foreach ($config['directions'] as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="fpm-field">
                    <span>Fiber count</span>
                    <input type="number" min="1" max="144" name="fiber_count" value="2">
                </label>
                <label class="fpm-field">
                    <span>Notes</span>
                    <textarea name="notes" rows="2"></textarea>
                </label>
                <div class="fpm-form__actions">
                    <button type="submit" class="fpm-btn fpm-btn--primary">Save cable</button>
                    <button type="button" class="fpm-btn fpm-btn--danger" id="fpm-delete-edge" hidden>Delete</button>
                </div>
            </form>

            <div id="fpm-help" class="fpm-help">
                <p><strong>How to use</strong></p>
                <ul>
                    <li><strong>Pan</strong> — map টেনে ঘুরান; <strong>Satellite</strong> toggle দিয়ে aerial view</li>
                    <li><strong>+ Node</strong> — splitter/POP add; PON name manually set করুন</li>
                    <li><strong>Cable</strong> — দুই node click → meter, direction, color</li>
                    <li><strong>Import</strong> — POP, OLT, customer GPS auto pin + drop cable</li>
                    <li>Customer add করলে ONU PON auto map এ দেখাবে</li>
                </ul>
            </div>
        </aside>

        <div class="fpm-map-wrap" wire:ignore>
            <div class="fpm-map-tools">
                <button type="button" class="fpm-map-tool fpm-map-tool--active" data-basemap="street">Street</button>
                <button type="button" class="fpm-map-tool" data-basemap="satellite">Satellite</button>
                <button type="button" class="fpm-map-tool" id="gis-basemap-dark" hidden>Dark</button>
            </div>
            <div id="fiber-plant-map" class="fpm-map"></div>
        </div>
    </div>

    <button type="button" class="gis-drawer-toggle" id="gis-drawer-toggle" aria-label="Intelligence panel">Intelligence</button>

    <aside class="gis-drawer" id="gis-drawer" aria-label="GIS Intelligence">
        <div class="gis-drawer__head">
            <strong>Network Intelligence</strong>
            <button type="button" id="gis-drawer-close" aria-label="Close">✕</button>
        </div>
        <div class="gis-drawer__tabs">
            <button type="button" class="gis-tab gis-tab--active" data-gis-tab="faults">Faults (<span id="gis-fault-count">0</span>)</button>
            <button type="button" class="gis-tab" data-gis-tab="rca">RCA</button>
            <button type="button" class="gis-tab" data-gis-tab="layers">Layers</button>
            <button type="button" class="gis-tab" data-gis-tab="timeline">Timeline</button>
        </div>
        <div class="gis-drawer__body">
            <div data-gis-panel="faults" id="gis-fault-list"></div>
            <div data-gis-panel="rca" id="gis-rca-cards" hidden><p class="gis-empty">Select a customer or fault for RCA.</p></div>
            <div data-gis-panel="layers" hidden>
                <label class="gis-layer-row"><input type="checkbox" id="gis-layer-offline"> Offline heatmap</label>
                <label class="gis-layer-row"><input type="checkbox" id="gis-layer-weak"> Weak RX heatmap</label>
                <label class="gis-layer-row"><input type="checkbox" id="gis-layer-faults" checked> Fault markers</label>
                <label class="gis-layer-row"><input type="checkbox" id="gis-layer-techs"> Technicians</label>
            </div>
            <div data-gis-panel="timeline" hidden>
                <p id="gis-timeline-label" class="gis-empty">Playback network events</p>
                <input type="range" id="gis-timeline-slider" min="0" max="0" value="0" style="width:100%">
                <button type="button" class="fpm-btn fpm-btn--ghost" id="gis-timeline-play">▶ Play</button>
                <div id="gis-timeline-list"></div>
            </div>
        </div>
    </aside>

    <div class="gis-mobile-fab">
        <button type="button" id="gis-mobile-search">Search</button>
        <button type="button" id="gis-mobile-layers">Layers</button>
    </div>

    <div class="gis-core-modal" id="gis-core-modal" hidden>
        <div class="gis-core-modal__inner">
            <div id="gis-core-modal-body"></div>
            <button type="button" class="fpm-btn fpm-btn--ghost" data-gis-core-close>Close</button>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js" crossorigin=""></script>
    <script src="{{ asset('js/fiber-plant-map.js') }}?v={{ @filemtime(public_path('js/fiber-plant-map.js')) ?: 1 }}" data-cfasync="false"></script>
    <script src="{{ asset('js/gis-intelligence.js') }}?v={{ @filemtime(public_path('js/gis-intelligence.js')) ?: 1 }}" data-cfasync="false"></script>
    <script data-cfasync="false">
        function ispInitFiberPlantMap() {
            if (typeof window.IspFiberPlantMap === 'undefined') {
                return;
            }
            if (typeof L === 'undefined') {
                return;
            }
            const mapEl = document.getElementById('fiber-plant-map');
            if (!mapEl || !@this) {
                return;
            }
            window.IspFiberPlantMap.init({
                mapEl: 'fiber-plant-map',
                payload: @json($payload),
                wire: @this,
            });
            window.__gisWire = @this;
        }

        document.addEventListener('livewire:init', function () {
            Livewire.on('isp-fiber-map-refresh', function (data) {
                const next = data?.payload ?? data?.[0]?.payload ?? null;
                if (window.IspFiberPlantMap?.refreshPayload) {
                    window.IspFiberPlantMap.refreshPayload(next);
                    window.IspGisIntelligence?.refresh?.();
                } else {
                    ispInitFiberPlantMap();
                }
            });
        });

        document.addEventListener('DOMContentLoaded', ispInitFiberPlantMap);
        document.addEventListener('livewire:navigated', ispInitFiberPlantMap);

        if (document.readyState !== 'loading') {
            ispInitFiberPlantMap();
        }
    </script>
</x-filament-panels::page>
