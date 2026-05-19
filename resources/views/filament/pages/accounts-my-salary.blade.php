@php
    $employee = $this->employee;
@endphp

<x-filament-panels::page class="isp-accounts-page">
    <div class="space-y-5">
        <section class="isp-accounts-hero isp-accounts-hero--compact">
            <div class="isp-accounts-hero__main">
                <p class="isp-accounts-hero__eyebrow">Accounts</p>
                <h2 class="isp-accounts-hero__title">My salary</h2>
                <p class="isp-accounts-hero__sub">Your employee profile, wallet balance, and recent payroll history.</p>
            </div>
        </section>

        @if ($employee === null)
            <section class="isp-accounts-info-card">
                <p>No employee record matched your login email or name. Contact HR to link your staff profile.</p>
                <p class="mt-3">
                    <a href="{{ $this->payrollUrl }}" class="text-primary-600 underline">Open payroll module</a>
                </p>
            </section>
        @else
            <section class="isp-accounts-stats">
                <div class="isp-accounts-stat isp-accounts-stat--primary">
                    <span class="isp-accounts-stat__label">Monthly salary</span>
                    <strong>{{ number_format($employee->base_salary, 2) }} BDT</strong>
                </div>
                <div class="isp-accounts-stat">
                    <span class="isp-accounts-stat__label">Wallet</span>
                    <strong>{{ number_format($employee->wallet_balance, 2) }} BDT</strong>
                </div>
                <div class="isp-accounts-stat">
                    <span class="isp-accounts-stat__label">Department</span>
                    <strong>{{ $employee->department ?? '—' }}</strong>
                </div>
            </section>

            <section class="isp-accounts-table-card">
                <div class="isp-accounts-table-card__head">
                    <h3>Payroll history</h3>
                </div>
                @if (count($this->payrollHistory) === 0)
                    <p class="p-4 text-sm text-gray-500">No payroll runs recorded yet.</p>
                @else
                    <div class="isp-accounts-scroll-table">
                        <table class="isp-accounts-data-table">
                            <thead>
                                <tr>
                                    <th>Period</th>
                                    <th class="text-right">Gross</th>
                                    <th class="text-right">Deductions</th>
                                    <th class="text-right">Net</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->payrollHistory as $row)
                                    <tr>
                                        <td>{{ $row['period'] }}</td>
                                        <td class="text-right">{{ number_format($row['gross'], 2) }}</td>
                                        <td class="text-right">{{ number_format($row['deductions'], 2) }}</td>
                                        <td class="text-right font-semibold">{{ number_format($row['net'], 2) }}</td>
                                        <td><span class="isp-accounts-pill">{{ $row['status'] }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif
    </div>
</x-filament-panels::page>
