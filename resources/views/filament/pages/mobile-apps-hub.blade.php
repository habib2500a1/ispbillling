@php
    $stats = $this->getStats();
    $base = url('/api/v1');
    $statCards = [
        ['label' => 'FCM status', 'value' => $stats['fcm_enabled'] ? 'Enabled' : 'Disabled', 'hint' => 'Push notification transport', 'class' => $stats['fcm_enabled'] ? 'isp-hub-stat--emerald' : 'isp-hub-stat--amber'],
        ['label' => 'Customer devices', 'value' => (string) $stats['customer_devices'], 'hint' => 'Registered app devices', 'class' => 'isp-hub-stat--cyan'],
        ['label' => 'Technician devices', 'value' => (string) $stats['technician_devices'], 'hint' => 'Field app devices', 'class' => 'isp-hub-stat--violet'],
        ['label' => 'bKash', 'value' => $stats['bkash_enabled'] ? 'Enabled' : 'Disabled', 'hint' => 'Payment handoff for app', 'class' => $stats['bkash_enabled'] ? 'isp-hub-stat--teal' : 'isp-hub-stat--slate'],
    ];
    $apiCards = [
        ['tone' => 'violet', 'title' => 'Customer app API', 'base' => $base.'/customer', 'routes' => ['POST /login', 'GET /dashboard', 'GET /bills · GET /bills/{id}', 'POST /bills/{id}/pay -> bKash URL', 'GET /usage/live', 'GET|POST /tickets', 'POST /devices (FCM token)']],
        ['tone' => 'emerald', 'title' => 'Technician app API', 'base' => $base, 'routes' => ['POST /auth/login', 'GET /technician/field-visits', 'PATCH /technician/field-visits/{id}', 'POST /technician/devices']],
    ];
@endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6">
        <x-isp.hub-hero
            eyebrow="Mobile platform"
            title="Mobile app features"
            description="REST APIs for customer and technician apps with push notifications, bill payment, and live usage monitoring."
            class="isp-hub-hero--cyan"
        >
            <div class="isp-hub-toolbar">
                <div class="isp-hub-toolbar__meta">
                    <span class="isp-hub-results">{{ $stats['customer_devices'] }} customer devices</span>
                    <span class="isp-hub-section__meta">{{ $stats['technician_devices'] }} technician devices</span>
                </div>
            </div>
        </x-isp.hub-hero>

        <x-isp.hub-stat-grid :stats="$statCards" />

        <section class="isp-hub-section">
            <div class="isp-hub-section__head">
                <div>
                    <h2 class="isp-hub-section__title">API surfaces</h2>
                    <p class="isp-hub-section__desc">Key mobile API namespaces used by customer and technician apps.</p>
                </div>
                <span class="isp-hub-section__meta">Bearer auth</span>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($apiCards as $card)
                    <div class="isp-ops-panel">
                        <div class="isp-ops-panel__head">
                            <div>
                                <h3 class="isp-ops-panel__title">{{ $card['title'] }}</h3>
                                <p class="isp-ops-panel__desc">Base: <code class="text-xs">{{ $card['base'] }}</code></p>
                            </div>
                            <span class="isp-ops-pill isp-ops-pill--ok">{{ ucfirst($card['tone']) }}</span>
                        </div>
                        <div class="isp-ops-list">
                            @foreach ($card['routes'] as $route)
                                <div class="isp-ops-list__item">
                                    <div class="isp-ops-list__primary">
                                        <p class="isp-ops-list__title"><code class="text-xs">{{ $route }}</code></p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="isp-ops-panel">
            <div class="isp-ops-panel__head">
                <div>
                    <h3 class="isp-ops-panel__title">Environment</h3>
                    <p class="isp-ops-panel__desc">Runtime readiness for mobile notifications and payment handoff.</p>
                </div>
                <span class="isp-ops-pill {{ $stats['fcm_enabled'] ? 'isp-ops-pill--ok' : 'isp-ops-pill--warn' }}">{{ $stats['fcm_enabled'] ? 'FCM ready' : 'FCM pending' }}</span>
            </div>
            <div class="isp-ops-list">
                <div class="isp-ops-list__item"><div class="isp-ops-list__primary"><p class="isp-ops-list__title">FCM_ENABLED</p><p class="isp-ops-list__meta font-mono">{{ $stats['fcm_enabled'] ? 'true' : 'false' }}</p></div></div>
                <div class="isp-ops-list__item"><div class="isp-ops-list__primary"><p class="isp-ops-list__title">BKASH_ENABLED</p><p class="isp-ops-list__meta font-mono">{{ $stats['bkash_enabled'] ? 'true' : 'false' }}</p></div></div>
                <div class="isp-ops-list__item"><div class="isp-ops-list__primary"><p class="isp-ops-list__title">FCM_SERVER_KEY</p><p class="isp-ops-list__meta font-mono">{{ config('mobile.fcm_server_key') ? 'set' : 'not set' }}</p></div></div>
                <div class="isp-ops-list__item"><div class="isp-ops-list__primary"><p class="isp-ops-list__title">Auth tokens</p><p class="isp-ops-list__meta">Use Authorization: Bearer {token}. Customer tokens expire in {{ config('mobile.customer_token_expiry_days') }} days.</p></div></div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
