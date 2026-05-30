@php
    $payload = $this->getMapPayload();
    $stats = $payload['stats'];
    $config = $payload['config'];
@endphp

<x-filament-panels::page class="isp-fiber-plant-page">
    <link rel="stylesheet" href="{{ asset('css/fiber-plant-map.css') }}?v={{ @filemtime(public_path('css/fiber-plant-map.css')) ?: 1 }}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">

    <section class="fpm-hero">
        <div>
            <p class="fpm-hero__eyebrow">Outside plant · GPON field map</p>
            <h2 class="fpm-hero__title">Fiber cable & splitter map</h2>
            <p class="fpm-hero__sub">
                Splitter theke kon dike koto meter cable, kon color fiber, kon jaygay ki bosano — map e add/edit korun.
                TIA-598 standard color code support.
            </p>
        </div>
        <div class="fpm-hero__stats">
            <div><strong>{{ number_format($stats['nodes']) }}</strong><span>Nodes</span></div>
            <div><strong>{{ number_format($stats['edges']) }}</strong><span>Cables</span></div>
            <div><strong>{{ number_format($stats['total_cable_m'], 0) }}m</strong><span>Total fiber</span></div>
            <div><strong>{{ number_format($stats['splitters']) }}</strong><span>Splitters</span></div>
        </div>
    </section>

    <div class="fpm-layout">
        <aside class="fpm-panel" id="fpm-panel">
            <div class="fpm-toolbar">
                <button type="button" class="fpm-tool fpm-tool--active" data-mode="view" title="Select / pan">Select</button>
                <button type="button" class="fpm-tool" data-mode="add_node" title="Click map to add node">+ Node</button>
                <button type="button" class="fpm-tool" data-mode="draw_cable" title="Click two nodes">Cable</button>
                <button
                    type="button"
                    class="fpm-tool fpm-tool--ghost fpm-tool--import"
                    id="fpm-import"
                    wire:click="runImport"
                    wire:loading.attr="disabled"
                    wire:target="runImport"
                    title="Import POP, OLT, customer GPS"
                >
                    <span wire:loading.remove wire:target="runImport">Import</span>
                    <span wire:loading wire:target="runImport">Importing…</span>
                </button>
            </div>

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
                    <li><strong>+ Node</strong> — map e click kore splitter/pole/POP add korun</li>
                    <li><strong>Cable</strong> — dui node click kore length + color set korun</li>
                    <li><strong>Import</strong> — POP box, OLT, customer GPS auto add</li>
                    <li>Subscriber page e fiber path o dekha jabe</li>
                </ul>
            </div>
        </aside>

        <div class="fpm-map-wrap" wire:ignore>
            <div id="fiber-plant-map" class="fpm-map"></div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="{{ asset('js/fiber-plant-map.js') }}?v={{ @filemtime(public_path('js/fiber-plant-map.js')) ?: 1 }}" data-cfasync="false"></script>
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
        }

        document.addEventListener('livewire:init', function () {
            Livewire.on('isp-fiber-map-refresh', function (data) {
                const next = data?.payload ?? data?.[0]?.payload ?? null;
                if (window.IspFiberPlantMap?.refreshPayload) {
                    window.IspFiberPlantMap.refreshPayload(next);
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
