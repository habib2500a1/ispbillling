@php
    $stats = $this->getStats();
    $base = url('/api/v1');
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-2xl border border-cyan-200 bg-gradient-to-br from-cyan-50 via-white to-violet-50/50 p-6 shadow-sm dark:border-cyan-900/40 dark:from-cyan-950/40 dark:via-gray-900">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Mobile app features</h2>
            <p class="mt-2 max-w-3xl text-sm text-gray-600 dark:text-gray-400">
                REST API for customer app & technician app — push notifications, bill payment (bKash), live usage monitor.
            </p>
            <div class="mt-4 flex flex-wrap gap-2 text-xs">
                <span class="rounded-full px-3 py-1 font-semibold {{ $stats['fcm_enabled'] ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                    FCM {{ $stats['fcm_enabled'] ? 'enabled' : 'disabled' }}
                </span>
                <span class="rounded-full bg-white px-3 py-1 font-semibold shadow-sm dark:bg-gray-800">
                    {{ $stats['customer_devices'] }} customer devices · {{ $stats['technician_devices'] }} technician devices
                </span>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-violet-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-lg font-bold text-violet-700">Customer app API</h3>
                <p class="mt-1 text-sm text-gray-500">Base: <code class="text-xs">{{ $base }}/customer</code></p>
                <ul class="mt-4 space-y-2 font-mono text-xs text-slate-700 dark:text-slate-300">
                    <li>POST /login</li>
                    <li>GET /dashboard</li>
                    <li>GET /bills · GET /bills/{id}</li>
                    <li>POST /bills/{id}/pay → bKash URL</li>
                    <li>GET /usage/live</li>
                    <li>GET|POST /tickets</li>
                    <li>POST /devices (FCM token)</li>
                </ul>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-lg font-bold text-emerald-700">Technician app API</h3>
                <p class="mt-1 text-sm text-gray-500">Base: <code class="text-xs">{{ $base }}</code></p>
                <ul class="mt-4 space-y-2 font-mono text-xs text-slate-700 dark:text-slate-300">
                    <li>POST /auth/login</li>
                    <li>GET /technician/field-visits</li>
                    <li>PATCH /technician/field-visits/{id}</li>
                    <li>POST /technician/devices</li>
                </ul>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <h3 class="font-semibold text-gray-900 dark:text-white">Environment</h3>
            <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500">FCM_ENABLED</dt><dd class="font-mono">{{ $stats['fcm_enabled'] ? 'true' : 'false' }}</dd></div>
                <div><dt class="text-gray-500">BKASH_ENABLED</dt><dd class="font-mono">{{ $stats['bkash_enabled'] ? 'true' : 'false' }}</dd></div>
                <div><dt class="text-gray-500">FCM_SERVER_KEY</dt><dd class="font-mono">{{ config('mobile.fcm_server_key') ? '•••• set' : 'not set' }}</dd></div>
            </dl>
            <p class="mt-4 text-xs text-gray-500">Use Authorization: Bearer {token} on all authenticated routes. Customer tokens expire in {{ config('mobile.customer_token_expiry_days') }} days.</p>
        </div>
    </div>
</x-filament-panels::page>
