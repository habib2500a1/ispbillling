@php
    $stats = $this->getStats();
    $attendance = $this->getMonthAttendanceBreakdown();
@endphp

<x-filament-panels::page class="isp-hrm-page isp-hrm-reports-page">
    <div class="space-y-5">
        <section class="isp-hrm-employees-hero">
            <div class="isp-hrm-employees-hero__main">
                <p class="isp-hrm-employees-hero__eyebrow">HR Management</p>
                <h2 class="isp-hrm-employees-hero__title">HR Reports</h2>
                <p class="isp-hrm-employees-hero__sub">Snapshot of staff, attendance, payroll, and leave for {{ $stats['period_label'] }}.</p>
            </div>
            <div class="isp-hrm-employees-hero__stats">
                <div class="isp-hrm-employees-stat">
                    <span class="isp-hrm-employees-stat__label">Active staff</span>
                    <strong>{{ number_format($stats['active_employees']) }}</strong>
                </div>
                <div class="isp-hrm-employees-stat">
                    <span class="isp-hrm-employees-stat__label">Present today</span>
                    <strong>{{ number_format($stats['present_today']) }}</strong>
                </div>
                <div class="isp-hrm-employees-stat">
                    <span class="isp-hrm-employees-stat__label">Payroll YTD</span>
                    <strong>{{ number_format($stats['ytd_paid'], 0) }} <small>BDT</small></strong>
                </div>
                <div class="isp-hrm-employees-stat">
                    <span class="isp-hrm-employees-stat__label">Monthly gross</span>
                    <strong>{{ number_format($stats['monthly_gross'], 0) }} <small>BDT</small></strong>
                </div>
            </div>
        </section>

        <section class="isp-hrm-reports-grid">
            @foreach ($this->getReportLinks() as $link)
                <a href="{{ $link['url'] }}" class="isp-hrm-reports-card">
                    <span class="isp-hrm-reports-card__label">{{ $link['label'] }}</span>
                    <strong class="isp-hrm-reports-card__value">{{ $link['value'] }}</strong>
                </a>
            @endforeach
        </section>

        <div class="isp-hrm-reports-panels">
            <section class="isp-hrm-employees-table-card">
                <div class="isp-hrm-employees-table-card__head">
                    <h3 class="isp-hrm-employees-table-card__title">Attendance this month</h3>
                </div>
                <ul class="isp-hrm-reports-breakdown">
                    <li><span>Present</span><strong>{{ number_format($attendance['present']) }}</strong></li>
                    <li><span>Absent</span><strong>{{ number_format($attendance['absent']) }}</strong></li>
                    <li><span>Leave</span><strong>{{ number_format($attendance['leave']) }}</strong></li>
                    <li><span>Holiday</span><strong>{{ number_format($attendance['holiday']) }}</strong></li>
                </ul>
            </section>

            <section class="isp-hrm-employees-table-card">
                <div class="isp-hrm-employees-table-card__head">
                    <h3 class="isp-hrm-employees-table-card__title">Recent payroll runs</h3>
                </div>
                @forelse ($this->getRecentPayrollRuns() as $run)
                    <div class="isp-hrm-reports-run">
                        <span>{{ $run['month'] }}</span>
                        <span class="isp-hrm-reports-run__status">{{ ucfirst($run['status']) }}</span>
                        <strong>{{ number_format($run['net'], 0) }} BDT</strong>
                    </div>
                @empty
                    <p class="isp-hrm-reports-empty">No payroll runs yet. Generate from Payroll Generation.</p>
                @endforelse
            </section>
        </div>
    </div>
</x-filament-panels::page>
