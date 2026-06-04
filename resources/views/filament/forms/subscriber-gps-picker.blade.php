@php
    $defaultLat = (float) config('isp.default_map_lat', 23.8103);
    $defaultLng = (float) config('isp.default_map_lng', 90.4125);
@endphp

<div
    id="isp-subscriber-gps-picker"
    class="isp-subscriber-gps-picker"
    data-default-lat="{{ $defaultLat }}"
    data-default-lng="{{ $defaultLng }}"
>
    <label for="isp-subscriber-gps-combined" class="mb-1 block text-sm font-semibold text-gray-950 dark:text-white">
        GPS Coordinates (Lat, Long)
    </label>
    <div class="flex gap-2">
        <input
            type="text"
            id="isp-subscriber-gps-combined"
            class="fi-input block w-full min-w-0 flex-1 rounded-lg border-gray-300 bg-white px-3 py-2 font-mono text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 transition focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-gray-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
            placeholder="Fetching…"
            autocomplete="off"
            spellcheck="false"
        />
        <button
            type="button"
            id="isp-subscriber-gps-btn"
            class="inline-flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-lg border-2 border-primary-500 bg-white text-primary-600 shadow-sm hover:bg-primary-50 dark:border-primary-400 dark:bg-gray-900 dark:hover:bg-primary-950/40"
            title="Use my GPS"
            aria-label="Capture GPS location"
        >
            <x-filament::icon icon="heroicon-m-map-pin" class="h-5 w-5" />
        </button>
    </div>
    <p id="isp-subscriber-gps-status" class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Fetching location…</p>

    <div
        id="isp-subscriber-gps-map"
        class="mt-3 h-56 w-full overflow-hidden rounded-lg border border-gray-200 bg-gray-100 dark:border-gray-600 dark:bg-gray-800"
        wire:ignore
        role="img"
        aria-label="Location map"
    ></div>
</div>
