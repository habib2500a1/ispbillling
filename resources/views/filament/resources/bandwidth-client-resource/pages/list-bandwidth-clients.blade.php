@php
    $stats = $this->getBwStats();
@endphp

<x-filament-panels::page class="isp-bw-clients-page">
    <div class="space-y-5">
        <section class="isp-bw-clients-hero">
            <div class="isp-bw-clients-hero__main">
                <p class="isp-bw-clients-hero__eyebrow">BW Client</p>
                <h2 class="isp-bw-clients-hero__title">Bandwidth clients</h2>
                <p class="isp-bw-clients-hero__sub">
                    Wholesale / upstream buyers — profile total, payments, due invoices, and status in one place.
                </p>
            </div>
            <div class="isp-bw-clients-hero__stats">
                <div class="isp-bw-clients-stat">
                    <span class="isp-bw-clients-stat__label">Clients</span>
                    <strong>{{ number_format($stats['total']) }}</strong>
                </div>
                <div class="isp-bw-clients-stat">
                    <span class="isp-bw-clients-stat__label">Active</span>
                    <strong>{{ number_format($stats['active']) }}</strong>
                </div>
                <div class="isp-bw-clients-stat">
                    <span class="isp-bw-clients-stat__label">Profile / mo</span>
                    <strong>{{ number_format($stats['profile_total'], 0) }}</strong>
                </div>
                <div class="isp-bw-clients-stat isp-bw-clients-stat--due">
                    <span class="isp-bw-clients-stat__label">Total due</span>
                    <strong>{{ number_format($stats['due_total'], 0) }}</strong>
                </div>
            </div>
        </section>

        <section class="isp-bw-clients-table-card">
            <p class="isp-bw-clients-table-card__hint">
                Search by client name, contact, or ID. Use status filter in the table toolbar.
            </p>
            {{ $this->table }}
        </section>
    </div>
</x-filament-panels::page>
