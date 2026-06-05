<x-filament-panels::page class="isp-hrm-page isp-hrm-policy-page">
    <form wire:submit="save" class="space-y-5">
        <section class="isp-hrm-employees-hero">
            <div class="isp-hrm-employees-hero__main">
                <p class="isp-hrm-employees-hero__eyebrow">HR Management</p>
                <h2 class="isp-hrm-employees-hero__title">HR Policy</h2>
                <p class="isp-hrm-employees-hero__sub">Office timing, attendance rules, late fines, and biometric sync.</p>
            </div>
            <a href="{{ \App\Filament\Pages\HrSalaryPoliciesPage::employeesUrl() }}" class="isp-hrm-policy-link">
                Employee salary list →
            </a>
        </section>

        <div class="isp-hrm-policy-grid">
            <section class="isp-hrm-employees-table-card">
                <h3 class="isp-hrm-policy-section-title">Office Timing</h3>
                <div class="isp-hrm-policy-fields">
                    <div>
                        <label>Office Start Time</label>
                        <input type="time" wire:model="office_start_time" class="isp-hrm-payroll-input" />
                    </div>
                    <div>
                        <label>Late Grace Time (Minutes)</label>
                        <input type="number" min="0" wire:model="late_grace_minutes" class="isp-hrm-payroll-input" />
                        <p class="isp-hrm-policy-hint">Arriving after start + grace marks employee as late.</p>
                    </div>
                </div>
            </section>

            <section class="isp-hrm-employees-table-card">
                <h3 class="isp-hrm-policy-section-title">Attendance Restrictions</h3>
                <div class="isp-hrm-policy-fields">
                    <div>
                        <label>Office Public IP Address</label>
                        <input type="text" wire:model="office_public_ip" placeholder="e.g. 103.12.45.67" class="isp-hrm-payroll-input" />
                    </div>
                    <div>
                        <label>Min Work Hours Before Checkout</label>
                        <input type="number" min="0" wire:model="min_work_hours_before_checkout" class="isp-hrm-payroll-input" />
                    </div>
                </div>
            </section>

            <section class="isp-hrm-employees-table-card">
                <h3 class="isp-hrm-policy-section-title">Late Deduction Rules</h3>
                <div class="isp-hrm-policy-fields isp-hrm-policy-fields--3">
                    <div>
                        <label>Allowed Late Days</label>
                        <input type="number" min="0" wire:model="allowed_late_days" class="isp-hrm-payroll-input" />
                    </div>
                    <div>
                        <label>Fine Amount (৳)</label>
                        <input type="number" min="0" step="0.01" wire:model="late_fine_amount" class="isp-hrm-payroll-input" />
                    </div>
                    <div>
                        <label>1-Day Salary Cut Trigger</label>
                        <input type="number" min="1" wire:model="late_salary_cut_trigger_days" class="isp-hrm-payroll-input" />
                    </div>
                    <div>
                        <label>PF % (payroll)</label>
                        <input type="number" min="0" max="100" step="0.1" wire:model="pf_percent" class="isp-hrm-payroll-input" />
                    </div>
                    <div>
                        <label>Absent day deduction %</label>
                        <input type="number" min="0" max="100" wire:model="absent_day_deduction_percent" class="isp-hrm-payroll-input" />
                    </div>
                </div>
            </section>

            <section class="isp-hrm-employees-table-card">
                <h3 class="isp-hrm-policy-section-title">Biometric Sync</h3>
                <p class="isp-hrm-policy-hint mb-2">API endpoint for ZKTeco / biometric devices:</p>
                <code class="isp-hrm-policy-code">{{ \App\Services\Hr\HrPolicySettings::biometricApiUrl() }}</code>
                <div class="mt-3">
                    <label>Secret API Key</label>
                    <input type="password" wire:model="biometric_api_secret" placeholder="Generate or enter a strong key" class="isp-hrm-payroll-input" />
                </div>
                <div class="mt-2 flex gap-2">
                    <x-filament::button type="button" wire:click="generateBiometricKey" color="gray" size="sm">Generate Random</x-filament::button>
                </div>
            </section>

            <section class="isp-hrm-employees-table-card">
                <h3 class="isp-hrm-policy-section-title">Public &amp; Govt. Holidays</h3>
                <label>One date per line (YYYY-MM-DD)</label>
                <textarea wire:model="public_holidays" rows="5" class="isp-hrm-payroll-input w-full mt-1"></textarea>
            </section>
        </div>

        @if (\App\Filament\Pages\HrSalaryPoliciesPage::canManagePayroll())
            <x-filament::button type="submit" color="primary">Save HR Policy</x-filament::button>
        @endif
    </form>
</x-filament-panels::page>
