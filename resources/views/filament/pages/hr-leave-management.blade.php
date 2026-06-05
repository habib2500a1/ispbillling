<x-filament-panels::page class="isp-hrm-page isp-hrm-leave-page">
    <div class="space-y-5">
        <section class="isp-hrm-employees-hero">
            <div class="isp-hrm-employees-hero__main">
                <p class="isp-hrm-employees-hero__eyebrow">HR Management</p>
                <h2 class="isp-hrm-employees-hero__title">Leave Management</h2>
                <p class="isp-hrm-employees-hero__sub">Manage employee leave requests — approved leave syncs to attendance.</p>
            </div>
        </section>
        <section class="isp-hrm-employees-table-card">
            {{ $this->table }}
        </section>
    </div>
</x-filament-panels::page>
