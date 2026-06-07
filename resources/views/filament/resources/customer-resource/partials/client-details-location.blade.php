@props(['contact' => [], 'address' => null, 'editUrl' => null, 'clientName' => null])

@php
    $hasGps = (bool) ($contact['has_gps'] ?? false);
    $googleMaps = $contact['google_maps'] ?? null;
    $fiberMap = $contact['fiber_map'] ?? null;
    $gpsDisplay = $contact['gps_display'] ?? null;
    $lat = $contact['gps_lat'] ?? null;
    $lng = $contact['gps_lng'] ?? null;
    $mapModalId = 'isp-cv-map-modal-' . md5(($lat ?? '0') . '|' . ($lng ?? '0') . '|' . ($clientName ?? 'client'));
    $editTabUrl = $editUrl ? $editUrl.(str_contains($editUrl, '?') ? '&' : '?').'tab=location-staff' : null;
@endphp

<section class="isp-cv-card isp-cv-card--location isp-cv-card--full">
    <div class="isp-cv-card__head isp-cv-location__head">
        <div class="isp-cv-location__head-main">
            <span class="isp-cv-location__badge" aria-hidden="true">
                <x-filament::icon icon="heroicon-o-map-pin" class="h-4 w-4" />
            </span>
            <div class="isp-cv-location__head-copy">
                <h3 class="isp-cv-card__title">Live location map</h3>
                @if ($hasGps)
                    <p class="isp-cv-location__subtitle">GPS pin saved</p>
                @else
                    <p class="isp-cv-location__subtitle">No pin yet</p>
                @endif
            </div>
        </div>

        @if ($hasGps)
            <div class="isp-cv-location__head-actions" role="toolbar" aria-label="Map actions">
                @if ($googleMaps)
                    <a href="{{ $googleMaps }}" class="isp-cv-location__action isp-cv-location__action--primary" target="_blank" rel="noopener">
                        <x-filament::icon icon="heroicon-o-map-pin" class="h-4 w-4" />
                        <span>Google Maps</span>
                    </a>
                @endif
                @if ($fiberMap)
                    <a href="{{ $fiberMap }}" class="isp-cv-location__action" target="_blank" rel="noopener">
                        <x-filament::icon icon="heroicon-o-map" class="h-4 w-4" />
                        <span>Fiber map</span>
                    </a>
                @endif
                <button type="button" class="isp-cv-location__action" data-map-open-fullscreen data-map-target="{{ $mapModalId }}">
                    <x-filament::icon icon="heroicon-o-arrows-pointing-out" class="h-4 w-4" />
                    <span>Full screen</span>
                </button>
                @if ($editTabUrl)
                    <a href="{{ $editTabUrl }}" class="isp-cv-location__action">
                        <x-filament::icon icon="heroicon-o-pencil-square" class="h-4 w-4" />
                        <span>Edit pin</span>
                    </a>
                @endif
            </div>
        @endif
    </div>

    @if (filled($address))
        <div class="isp-cv-location__address-bar">
            <x-filament::icon icon="heroicon-o-home-modern" class="h-4 w-4" />
            <p class="isp-cv-location__address">{{ $address }}</p>
        </div>
    @endif

    @if (($contact['district'] ?? null) || ($contact['thana'] ?? null))
        <p class="isp-cv-muted text-sm isp-cv-location__meta">
            @if ($contact['district'] ?? null)
                <span>{{ $contact['district'] }}</span>
            @endif
            @if (($contact['district'] ?? null) && ($contact['thana'] ?? null))
                <span aria-hidden="true"> · </span>
            @endif
            @if ($contact['thana'] ?? null)
                <span>{{ $contact['thana'] }}</span>
            @endif
        </p>
    @endif

    @if ($hasGps)
        <div class="isp-cv-location__coords">
            <x-filament::icon icon="heroicon-m-map-pin" class="h-3.5 w-3.5" />
            <span class="font-mono text-sm">{{ $gpsDisplay }}</span>
        </div>
        <div
            class="isp-cv-location__map"
            data-subscriber-view-map
            data-lat="{{ $lat }}"
            data-lng="{{ $lng }}"
            data-label="{{ $clientName ?? 'Client' }}"
            role="img"
            aria-label="Client location map"
        ></div>
    @else
        <div class="isp-cv-location__empty">
            <div class="isp-cv-location__empty-visual" aria-hidden="true">
                <x-filament::icon icon="heroicon-o-map" class="h-10 w-10" />
            </div>
            <p class="isp-cv-location__empty-title">No GPS pin saved yet</p>
            <p class="isp-cv-muted text-sm isp-cv-location__empty-hint">Set the client location from edit → Location &amp; staff → GPS map.</p>
            <div class="isp-cv-location__empty-actions">
                @if ($editTabUrl)
                    <a href="{{ $editTabUrl }}" class="isp-cv-location__action isp-cv-location__action--primary">
                        <x-filament::icon icon="heroicon-o-map-pin" class="h-4 w-4" />
                        <span>Add location</span>
                    </a>
                @endif
                @if ($fiberMap)
                    <a href="{{ $fiberMap }}" class="isp-cv-location__action">
                        <x-filament::icon icon="heroicon-o-map" class="h-4 w-4" />
                        <span>Fiber map</span>
                    </a>
                @endif
            </div>
        </div>
    @endif
</section>

@if ($hasGps)
    <div id="{{ $mapModalId }}" class="isp-cv-map-modal" hidden aria-hidden="true">
        <div class="isp-cv-map-modal__backdrop" data-map-close-fullscreen></div>
        <div class="isp-cv-map-modal__panel" role="dialog" aria-modal="true" aria-label="Full screen client map">
            <div class="isp-cv-map-modal__head">
                <strong>{{ $clientName ?? 'Client location' }}</strong>
                <span class="font-mono text-xs isp-cv-muted">{{ $gpsDisplay }}</span>
                <div class="isp-cv-location__head-actions">
                    @if ($googleMaps)
                        <a href="{{ $googleMaps }}" class="isp-cv-location__action isp-cv-location__action--primary" target="_blank" rel="noopener">
                            Google Maps
                        </a>
                    @endif
                    <button type="button" class="isp-cv-location__action" data-map-close-fullscreen aria-label="Close map">
                        <x-filament::icon icon="heroicon-o-x-mark" class="h-4 w-4" />
                        Close
                    </button>
                </div>
            </div>
            <div
                class="isp-cv-location__map isp-cv-location__map--modal"
                data-subscriber-view-map
                data-lat="{{ $lat }}"
                data-lng="{{ $lng }}"
                data-label="{{ $clientName ?? 'Client' }}"
                role="img"
                aria-label="Full screen client location map"
            ></div>
        </div>
    </div>
@endif
