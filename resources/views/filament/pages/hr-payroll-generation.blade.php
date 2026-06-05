@php
    $run = $this->currentRun;
    $items = $this->sheetItems;
@endphp

<x-filament-panels::page class="isp-hrm-page isp-hrm-payroll-page">
    <div class="space-y-5">
        <section class="isp-hrm-employees-hero">
            <div class="isp-hrm-employees-hero__main">
                <p class="isp-hrm-employees-hero__eyebrow">HR Management</p>
                <h2 class="isp-hrm-employees-hero__title">Payroll &amp; Salary</h2>
                <p class="isp-hrm-employees-hero__sub">Generate monthly salaries, apply bonuses, and process payouts.</p>
            </div>
        </section>

        <section class="isp-hrm-payroll-toolbar">
            <div class="isp-hrm-payroll-toolbar__filters">
                <div>
                    <label>Month</label>
                    <select wire:model.live="periodMonth" class="isp-hrm-payroll-input">
                        @foreach (range(1, 12) as $m)
                            <option value="{{ $m }}">{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Year</label>
                    <input type="number" wire:model.live="periodYear" min="2020" max="2099" class="isp-hrm-payroll-input" />
                </div>
                <div>
                    <label>Bonus</label>
                    <select wire:model="bonus" class="isp-hrm-payroll-input">
                        <option value="none">No Bonus</option>
                        <option value="five_percent">5% Bonus</option>
                        <option value="ten_percent">10% Bonus</option>
                    </select>
                </div>
            </div>
            <div class="isp-hrm-payroll-toolbar__actions">
                @if ($run)
                    <x-filament::button color="gray" wire:click="viewRun" icon="heroicon-o-eye">
                        View Records
                    </x-filament::button>
                @endif
                @if (\App\Filament\Pages\HrPayrollGenerationPage::canManagePayroll())
                    <x-filament::button color="success" wire:click="generatePayroll" icon="heroicon-o-calculator">
                        Generate / Refresh Payroll
                    </x-filament::button>
                @endif
            </div>
        </section>

        <section class="isp-hrm-employees-table-card">
            <div class="isp-hrm-employees-table-card__head">
                <h3 class="isp-hrm-employees-table-card__title">Salary Sheet : {{ $this->periodLabel }}</h3>
                @if ($run)
                    <span class="isp-hrm-employees-table-card__badge">{{ ucfirst($run->status) }} · Net {{ number_format((float) $run->total_net, 0) }} BDT</span>
                @endif
            </div>

            @if ($run && $items && $items->count() > 0)
                <div class="isp-hrm-payroll-sheet-wrap">
                    <table class="isp-hrm-payroll-sheet">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Basic</th>
                                <th>Auto deductions<br><small>Late+Absent+ADV+PF</small></th>
                                <th>Allowances<br><small>Bonus</small></th>
                                <th>Manual ded.</th>
                                <th>Net salary</th>
                                <th>Status</th>
                                <th>Due</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                <tr>
                                    <td>{{ $item->employee?->name }}</td>
                                    <td>{{ number_format((float) $item->basic_salary, 2) }}</td>
                                    <td>{{ number_format((float) $item->auto_deductions, 2) }}</td>
                                    <td>{{ number_format((float) $item->allowances, 2) }}</td>
                                    <td>{{ number_format((float) $item->manual_deduction, 2) }}</td>
                                    <td class="isp-hrm-payroll-sheet__net">{{ number_format((float) $item->net_salary, 2) }}</td>
                                    <td><span class="isp-hrm-payroll-badge">{{ ucfirst($item->payment_status ?? $run->status) }}</span></td>
                                    <td>{{ number_format((float) $item->amount_due, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $items->links() }}</div>
            @else
                <p class="isp-hrm-reports-empty px-2 py-6 text-center">
                    No payroll records generated yet. Click «Generate / Refresh Payroll» to calculate salaries for this month.
                </p>
            @endif
        </section>
    </div>
</x-filament-panels::page>
