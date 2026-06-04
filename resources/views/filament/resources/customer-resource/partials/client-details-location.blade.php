@props(['contact' => [], 'address' => null, 'notes' => null])

@php
    $hasGps = (bool) ($contact['has_gps'] ?? false);
    $googleMaps = $contact['google_maps'] ?? null;
    $fiberMap = $contact['fiber_map'] ?? null;
    $gpsDisplay = $contact['gps_display'] ?? null;
@endphp

<section class="isp-cv-card isp-cv-card--location">
    <div class="isp-cv-card__head">
        <h3 class="isp-cv-card__title">Location</h3>
        @if ($hasGps && $googleMaps)
            <a href="{{ $googleMaps }}" class="isp-cv-link" target="_blank" rel="noopener">
                View map
            </a>
        @endif
    </div>

    @if (filled($address))
        <p class="isp-cv-location__address">{{ $address }}</p>
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
            <span class="font-mono text-sm">{{ $gpsDisplay }}</span>
            <div class="isp-cv-location__links">
                @if ($googleMaps)
                    <a href="{{ $googleMaps }}" class="isp-cv-location__chip" target="_blank" rel="noopener">
                        <x-filament::icon icon="heroicon-o-map-pin" class="h-3.5 w-3.5" />
                        Google Maps
                    </a>
                @endif
                @if ($fiberMap)
                    <a href="{{ $fiberMap }}" class="isp-cv-location__chip" target="_blank" rel="noopener">
                        <x-filament::icon icon="heroicon-o-map" class="h-3.5 w-3.5" />
                        Fiber map
                    </a>
                @endif
            </div>
        </div>
        <div
            class="isp-cv-location__map"
            data-subscriber-view-map
            data-lat="{{ $contact['gps_lat'] }}"
            data-lng="{{ $contact['gps_lng'] }}"
            role="img"
            aria-label="Client location map"
        ></div>
    @else
        <p class="isp-cv-muted text-sm">No GPS saved — edit profile to pin location on map.</p>
        @if ($fiberMap)
            <a href="{{ $fiberMap }}" class="isp-cv-link text-sm mt-1 inline-flex">Open fiber plant map →</a>
        @endif
    @endif
</section>
