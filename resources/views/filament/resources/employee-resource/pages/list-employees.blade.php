@php
    $stats = $this->getEmployeeStats();
@endphp

<x-filament-panels::page class="isp-hrm-employees-page">
    <div class="space-y-5">
        <section class="isp-hrm-employees-hero">
            <div class="isp-hrm-employees-hero__main">
                <p class="isp-hrm-employees-hero__eyebrow">HRM</p>
                <h2 class="isp-hrm-employees-hero__title">Employees</h2>
                <p class="isp-hrm-employees-hero__sub">
                    Track employee status, salary due, wallet balance, and quick actions.
                </p>
            </div>
            <div class="isp-hrm-employees-hero__stats">
                <div class="isp-hrm-employees-stat">
                    <span class="isp-hrm-employees-stat__label">Active</span>
                    <strong>{{ number_format($stats['active']) }}</strong>
                </div>
                <div class="isp-hrm-employees-stat">
                    <span class="isp-hrm-employees-stat__label">Total</span>
                    <strong>{{ number_format($stats['total']) }}</strong>
                </div>
                <div class="isp-hrm-employees-stat">
                    <span class="isp-hrm-employees-stat__label">Monthly gross</span>
                    <strong>{{ number_format($stats['monthly_gross'], 0) }} <small>BDT</small></strong>
                </div>
                <div class="isp-hrm-employees-stat">
                    <span class="isp-hrm-employees-stat__label">Wallet pool</span>
                    <strong>{{ number_format($stats['wallet_total'], 0) }} <small>BDT</small></strong>
                </div>
                </div>
        </section>

        <section class="isp-hrm-employees-filters-card">
            <p class="isp-hrm-employees-filters-card__title">Search &amp; filter</p>
            <p class="isp-hrm-employees-filters-card__hint">
                Use the table filters below for department and status. Search matches name, ID, phone, or email.
            </p>
        </section>

        <section class="isp-hrm-employees-table-card">
            <div class="isp-hrm-employees-table-card__head">
                <h3 class="isp-hrm-employees-table-card__title">Employees list</h3>
                @if ($stats['inactive'] > 0)
                    <span class="isp-hrm-employees-table-card__badge">{{ $stats['inactive'] }} inactive</span>
                @endif
            </div>
            {{ $this->table }}
        </section>
    </div>
</x-filament-panels::page>
