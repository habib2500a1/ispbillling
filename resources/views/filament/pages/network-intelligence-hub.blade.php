@php
    $stats = $this->getStats();
    $statCards = [
        ['label' => 'OLTs', 'value' => (string) $stats['olts'], 'hint' => 'Provisioned line terminals', 'class' => 'isp-hub-stat--violet'],
        ['label' => 'ONUs online', 'value' => $stats['onus_online'].' / '.$stats['onus'], 'hint' => 'Connected optical units', 'class' => 'isp-hub-stat--emerald'],
        ['label' => 'ONUs offline', 'value' => (string) $stats['onus_offline'], 'hint' => 'Needs attention', 'class' => 'isp-hub-stat--danger', 'valueClass' => 'isp-hub-stat-value--danger'],
        ['label' => 'NetFlow (24h)', 'value' => number_format($stats['flows_24h']), 'hint' => 'Traffic events processed', 'class' => 'isp-hub-stat--cyan'],
    ];
@endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6">
        <x-isp.hub-hero
            eyebrow="Network intelligence"
            title="Network intelligence"
            description="ONU/OLT integration, GPON profiles, SNMP monitoring, and NetFlow traffic analysis in one workspace."
            class="isp-hub-hero--cyan"
        >
            <div class="isp-hub-toolbar">
                <div class="isp-hub-toolbar__meta">
                    <span class="isp-hub-results">SNMP {{ $stats['snmp_available'] ? 'enabled' : 'missing' }}</span>
                    <span class="isp-hub-section__meta">Last poll: {{ $stats['last_poll'] ?? 'never' }}</span>
                    @if ($stats['last_poll_ok'] !== null)
                        <span class="isp-hub-section__meta">{{ $stats['last_poll_ok'] ? 'Poll OK' : 'Poll failed' }}</span>
                    @endif
                </div>
            </div>
        </x-isp.hub-hero>

        <x-isp.hub-stat-grid :stats="$statCards" />

        <x-isp.hub-section-nav group="Network" :hub-url="\App\Filament\Pages\NetworkIntelligenceHub::getUrl()" hub-label="Network center" />

        <x-isp.hub-module-grid group="Network" />

        <section class="isp-ops-panel">
            <div class="isp-ops-panel__head">
                <div>
                    <h3 class="isp-ops-panel__title">Automation</h3>
                    <p class="isp-ops-panel__desc">Core commands and ingest flows used for optical polling, NetFlow processing, and ONU metadata sync.</p>
                </div>
                <span class="isp-ops-pill isp-ops-pill--ok">Automation ready</span>
            </div>
            <div class="isp-ops-list">
                <div class="isp-ops-list__item">
                    <div class="isp-ops-list__primary">
                        <p class="isp-ops-list__title"><code class="text-xs">isp:poll-olt-intelligence</code></p>
                        <p class="isp-ops-list__meta">SNMP poll all OLTs every 10 minutes.</p>
                    </div>
                </div>
                <div class="isp-ops-list__item">
                    <div class="isp-ops-list__primary">
                        <p class="isp-ops-list__title"><code class="text-xs">isp:process-netflow-inbox</code></p>
                        <p class="isp-ops-list__meta">Import JSON files from <code class="text-xs">storage/app/netflow/inbox/</code>.</p>
                    </div>
                </div>
                <div class="isp-ops-list__item">
                    <div class="isp-ops-list__primary">
                        <p class="isp-ops-list__title"><code class="text-xs">POST /api/webhooks/netflow-ingest</code></p>
                        <p class="isp-ops-list__meta">Send data with header <code class="text-xs">X-Netflow-Secret</code>.</p>
                    </div>
                </div>
                <div class="isp-ops-list__item">
                    <div class="isp-ops-list__primary">
                        <p class="isp-ops-list__title"><code class="text-xs">isp:sync-onu-status-from-meta</code></p>
                        <p class="isp-ops-list__meta">Sync ONU optical/status using stored <code class="text-xs">devices.meta</code> keys.</p>
                    </div>
                </div>
            </div>
        </section>

        <x-isp.hub-footer />
    </div>
</x-filament-panels::page>
