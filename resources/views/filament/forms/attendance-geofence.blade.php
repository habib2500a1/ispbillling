@php
    $offices = \App\Models\AttendanceOfficeLocation::query()
        ->where('is_active', true)
        ->orderByDesc('is_default')
        ->orderBy('name')
        ->get(['id', 'name', 'latitude', 'longitude', 'radius_meters', 'allowed_ips']);
    $defaultRadius = (int) config('attendance.default_radius_meters', 10);
@endphp

<div
    id="isp-attendance-geofence"
    class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40"
    data-offices='@json($offices)'
    data-client-ip="{{ request()->ip() }}"
    data-default-radius="{{ $defaultRadius }}"
>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-sm font-semibold text-gray-900 dark:text-white">Office GPS check</p>
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                «Present» requires your device within the office radius (usually {{ $defaultRadius }} m).
                Office Wi‑Fi IP is checked when configured.
            </p>
        </div>
        <button
            type="button"
            id="isp-attendance-gps-btn"
            class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-primary-500"
        >
            <x-filament::icon icon="heroicon-m-map-pin" class="h-4 w-4" />
            Use my GPS
        </button>
    </div>

    <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg bg-white px-3 py-2 dark:bg-gray-800">
            <dt class="text-xs text-gray-500 dark:text-gray-400">Your IP</dt>
            <dd class="font-mono text-xs font-medium text-gray-900 dark:text-white" id="isp-attendance-client-ip">{{ request()->ip() }}</dd>
        </div>
        <div class="rounded-lg bg-white px-3 py-2 dark:bg-gray-800">
            <dt class="text-xs text-gray-500 dark:text-gray-400">Distance from office</dt>
            <dd class="font-semibold text-gray-900 dark:text-white" id="isp-attendance-distance">—</dd>
        </div>
        <div class="rounded-lg bg-white px-3 py-2 dark:bg-gray-800">
            <dt class="text-xs text-gray-500 dark:text-gray-400">Allowed radius</dt>
            <dd class="font-semibold text-gray-900 dark:text-white" id="isp-attendance-radius">—</dd>
        </div>
        <div class="rounded-lg bg-white px-3 py-2 dark:bg-gray-800">
            <dt class="text-xs text-gray-500 dark:text-gray-400">Status</dt>
            <dd class="font-semibold" id="isp-attendance-gps-status">Select office & capture GPS</dd>
        </div>
    </dl>

    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400" id="isp-attendance-gps-hint"></p>
</div>

<script src="{{ asset('js/attendance-geofence.js') }}?v={{ @filemtime(public_path('js/attendance-geofence.js')) ?: 1 }}" data-cfasync="false"></script>
