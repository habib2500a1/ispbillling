@php
    $s = $setup;
@endphp

<x-filament::section>
    <x-slot name="heading">URLs & device API setup</x-slot>
    <x-slot name="description">
        Set <code class="text-xs">APP_URL</code> in <code class="text-xs">.env</code> to your public site (e.g. https://bill.flixbd.xyz).
        RCL SMS APK uses <strong>API base</strong> + <strong>device key</strong> below.
    </x-slot>

    <dl class="grid gap-4 text-sm">
        <div>
            <dt class="font-medium text-gray-700 dark:text-gray-300">Site base (customer checkout)</dt>
            <dd class="mt-1">
                <code class="block rounded-lg bg-gray-100 dark:bg-gray-800 px-3 py-2 text-xs break-all select-all">{{ $s['site_base'] }}</code>
            </dd>
        </div>
        <div>
            <dt class="font-medium text-gray-700 dark:text-gray-300">API base (mobile / RCL SMS)</dt>
            <dd class="mt-1">
                <code class="block rounded-lg bg-emerald-100 dark:bg-emerald-900/40 px-3 py-2 text-xs break-all select-all font-semibold text-emerald-900 dark:text-emerald-100">{{ $s['api_base'] }}</code>
                <p class="mt-1 text-xs text-gray-500">Paste this in RCL SMS → API base URL (not the full ingest path).</p>
            </dd>
        </div>
        <div>
            <dt class="font-medium text-gray-700 dark:text-gray-300">Device SMS ingest (POST)</dt>
            <dd class="mt-1">
                <code class="block rounded-lg bg-gray-100 dark:bg-gray-800 px-3 py-2 text-xs break-all select-all">{{ $s['device_ingest_url'] }}</code>
                <p class="mt-1 text-xs text-gray-500">Header: <code>{{ $s['header_name'] }}</code> = device key from this page.</p>
            </dd>
        </div>
        <div>
            <dt class="font-medium text-gray-700 dark:text-gray-300">Staff app ingest (Sanctum)</dt>
            <dd class="mt-1">
                <code class="block rounded-lg bg-gray-100 dark:bg-gray-800 px-3 py-2 text-xs break-all select-all">{{ $s['staff_ingest_url'] }}</code>
            </dd>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <dt class="font-medium text-gray-700 dark:text-gray-300">bKash personal pay page</dt>
                <dd class="mt-1">
                    <code class="block rounded-lg bg-gray-100 dark:bg-gray-800 px-3 py-2 text-xs break-all select-all">{{ $s['bkash_pay_url'] }}</code>
                </dd>
            </div>
            <div>
                <dt class="font-medium text-gray-700 dark:text-gray-300">Nagad personal pay page</dt>
                <dd class="mt-1">
                    <code class="block rounded-lg bg-gray-100 dark:bg-gray-800 px-3 py-2 text-xs break-all select-all">{{ $s['nagad_pay_url'] }}</code>
                </dd>
            </div>
        </div>
    </dl>

    <div class="mt-4 flex flex-wrap gap-2 text-xs">
        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 font-medium {{ $s['ingest_enabled'] ? 'bg-success-100 text-success-800 dark:bg-success-900/40 dark:text-success-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-800' }}">
            SMS ingest: {{ $s['ingest_enabled'] ? 'ON' : 'OFF' }}
        </span>
        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 font-medium {{ $s['device_key_set'] ? 'bg-success-100 text-success-800 dark:bg-success-900/40 dark:text-success-200' : 'bg-warning-100 text-warning-800 dark:bg-warning-900/40 dark:text-warning-200' }}">
            Device key: {{ $s['device_key_set'] ? 'configured' : 'not set' }}
        </span>
    </div>

    <details class="mt-4 group">
        <summary class="cursor-pointer text-sm font-medium text-primary-600 dark:text-primary-400">Test with curl</summary>
        <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-950 text-gray-100 p-3 text-xs select-all">{{ $s['curl_device'] }}</pre>
    </details>

    <details class="mt-2 group">
        <summary class="cursor-pointer text-sm font-medium text-primary-600 dark:text-primary-400">JSON body example</summary>
        <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-950 text-gray-100 p-3 text-xs select-all">{{ $s['json_body'] }}</pre>
    </details>
</x-filament::section>
