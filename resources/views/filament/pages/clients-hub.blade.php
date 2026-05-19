@php
    $stats = $this->getStats();
@endphp

<x-filament-panels::page class="isp-clients-page">
    <div class="space-y-6">
        <section class="isp-clients-hero">
            <div class="isp-clients-hero__main">
                <p class="isp-clients-hero__eyebrow">Clients</p>
                <h2 class="isp-clients-hero__title">Subscriber directory</h2>
                <p class="isp-clients-hero__sub">
                    Search, filter, and manage home &amp; reseller subscribers — billing, PPPoE, packages, and coverage.
                </p>
            </div>
            <div class="isp-clients-hero__stats">
                <div class="isp-clients-stat isp-clients-stat--primary">
                    <span class="isp-clients-stat__label">Total</span>
                    <strong>{{ number_format($stats['total'] ?? 0) }}</strong>
                </div>
                <div class="isp-clients-stat">
                    <span class="isp-clients-stat__label">Online</span>
                    <strong>{{ number_format($stats['online'] ?? 0) }}</strong>
                </div>
                <div class="isp-clients-stat">
                    <span class="isp-clients-stat__label">Active</span>
                    <strong>{{ number_format($stats['active'] ?? 0) }}</strong>
                </div>
                <div class="isp-clients-stat">
                    <span class="isp-clients-stat__label">Expired</span>
                    <strong>{{ number_format($stats['expired'] ?? 0) }}</strong>
                </div>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ \App\Filament\Resources\CustomerResource::getUrl('index') }}" class="isp-clients-hub-card group">
                <p class="isp-clients-hub-card__title">All clients</p>
                <p class="isp-clients-hub-card__sub">Directory with filters, bulk actions &amp; exports.</p>
            </a>
            <a href="{{ \App\Filament\Resources\CustomerResource::getUrl('create') }}" class="isp-clients-hub-card isp-clients-hub-card--accent group">
                <p class="isp-clients-hub-card__title">Add client</p>
                <p class="isp-clients-hub-card__sub">Register subscriber, package &amp; PPP credentials.</p>
            </a>
            <a href="{{ \App\Filament\Pages\OnlineClientsMonitoring::getUrl() }}" class="isp-clients-hub-card group">
                <p class="isp-clients-hub-card__title">Live PPP monitor</p>
                <p class="isp-clients-hub-card__sub">Online sessions, traffic &amp; kick tools.</p>
            </a>
            <a href="{{ \App\Filament\Pages\AreaWiseClientsReport::getUrl() }}" class="isp-clients-hub-card group">
                <p class="isp-clients-hub-card__title">Area-wise report</p>
                <p class="isp-clients-hub-card__sub">Clients grouped by area &amp; zone.</p>
            </a>
            <a href="{{ \App\Filament\Pages\ExportClientsReport::getUrl() }}" class="isp-clients-hub-card group">
                <p class="isp-clients-hub-card__title">Export clients</p>
                <p class="isp-clients-hub-card__sub">Download CSV for billing or audit.</p>
            </a>
            <a href="{{ \App\Filament\Pages\ImportClientsCsvPage::getUrl() }}" class="isp-clients-hub-card group">
                <p class="isp-clients-hub-card__title">Import CSV</p>
                <p class="isp-clients-hub-card__sub">Bulk onboard from spreadsheet.</p>
            </a>
        </div>

        <x-isp.hub-footer />
    </div>
</x-filament-panels::page>
