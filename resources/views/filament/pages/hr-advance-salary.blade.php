@php
    $stats = $this->getAdvanceStats();
@endphp

<x-filament-panels::page class="isp-hrm-page isp-hrm-advance-page">
    <div class="space-y-5">
        <section class="isp-hrm-employees-hero">
            <div class="isp-hrm-employees-hero__main">
                <p class="isp-hrm-employees-hero__eyebrow">HR Management</p>
                <h2 class="isp-hrm-employees-hero__title">Advance Salary</h2>
                <p class="isp-hrm-employees-hero__sub">Create advance salary requests with return policy — deducted on payroll or recovered manually.</p>
            </div>
            <div class="isp-hrm-employees-hero__stats">
                <div class="isp-hrm-employees-stat">
                    <span class="isp-hrm-employees-stat__label">Total advance</span>
                    <strong>{{ number_format($stats['total_advance'], 0) }} <small>BDT</small></strong>
                </div>
                <div class="isp-hrm-employees-stat">
                    <span class="isp-hrm-employees-stat__label">Open requests</span>
                    <strong>{{ number_format($stats['pending_count']) }}</strong>
                </div>
                <div class="isp-hrm-employees-stat">
                    <span class="isp-hrm-employees-stat__label">Active staff</span>
                    <strong>{{ number_format($stats['active_employees']) }}</strong>
                </div>
            </div>
        </section>
        <section class="isp-hrm-employees-table-card">
            <div class="isp-hrm-employees-table-card__head">
                <h3 class="isp-hrm-employees-table-card__title">Advance salary requests</h3>
            </div>
            {{ $this->table }}
        </section>
    </div>
</x-filament-panels::page>
