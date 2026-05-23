@php
    $stats = $this->getStats();
@endphp

<x-filament-panels::page class="isp-clients-page">
    <div class="space-y-6">
        <section class="isp-clients-hero">
            <div class="isp-clients-hero__main">
                <p class="isp-clients-hero__eyebrow">OLT</p>
                <h2 class="isp-clients-hero__title">OLT &amp; optical fiber</h2>
                <p class="isp-clients-hero__sub">
                    Aveis, BDCOM, Huawei — OLT list, ONU receive power (dBm), sync, and PON topology in one place.
                </p>
            </div>
            <div class="isp-clients-hero__stats">
                <div class="isp-clients-stat isp-clients-stat--primary">
                    <span class="isp-clients-stat__label">OLTs</span>
                    <strong>{{ number_format($stats['olts'] ?? 0) }}</strong>
                </div>
                <div class="isp-clients-stat">
                    <span class="isp-clients-stat__label">ONUs</span>
                    <strong>{{ number_format($stats['onus'] ?? 0) }}</strong>
                </div>
                <div class="isp-clients-stat">
                    <span class="isp-clients-stat__label">Online</span>
                    <strong>{{ number_format($stats['onus_online'] ?? 0) }}</strong>
                </div>
                <div class="isp-clients-stat">
                    <span class="isp-clients-stat__label">With RX dBm</span>
                    <strong>{{ number_format($stats['onus_with_rx'] ?? 0) }}</strong>
                </div>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ \App\Filament\Resources\OltResource::getUrl('index') }}" class="isp-clients-hub-card isp-clients-hub-card--accent group">
                <p class="isp-clients-hub-card__title">OLT list</p>
                <p class="isp-clients-hub-card__sub">Add Aveis / BDCOM OLT, SNMP sync, edit ONUs per port.</p>
            </a>
            <a href="{{ \App\Filament\Pages\OpticalMonitoringHub::getUrl() }}" class="isp-clients-hub-card group">
                <p class="isp-clients-hub-card__title">Optical Database</p>
                <p class="isp-clients-hub-card__sub">Receive power (RX dBm) — search ONU07/05, C1/P7, client.</p>
            </a>
            <a href="{{ \App\Filament\Pages\NetworkTopology::getUrl() }}" class="isp-clients-hub-card group">
                <p class="isp-clients-hub-card__title">Topology map</p>
                <p class="isp-clients-hub-card__sub">MikroTik → OLT → PON → ONU tree.</p>
            </a>
            <a href="{{ \App\Filament\Pages\OltMacTable::getUrl() }}" class="isp-clients-hub-card group">
                <p class="isp-clients-hub-card__title">OLT MAC table</p>
                <p class="isp-clients-hub-card__sub">MAC inventory from OLT SNMP.</p>
            </a>
            <a href="{{ \App\Filament\Pages\ManageOpticalLaserSettings::getUrl() }}" class="isp-clients-hub-card group">
                <p class="isp-clients-hub-card__title">Laser thresholds</p>
                <p class="isp-clients-hub-card__sub">RX/TX dBm bands, weak signal &amp; high laser alerts.</p>
            </a>
            <a href="{{ \App\Filament\Pages\NetworkIntelligenceHub::getUrl() }}" class="isp-clients-hub-card group">
                <p class="isp-clients-hub-card__title">SNMP &amp; NetFlow</p>
                <p class="isp-clients-hub-card__sub">Poll logs, interface status, traffic analysis.</p>
            </a>
        </div>
    </div>
</x-filament-panels::page>
