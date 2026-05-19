<x-filament-panels::page class="isp-reports-page">
    <div class="space-y-5">
        <section class="isp-reports-hero">
            <div class="isp-reports-hero__main">
                <p class="isp-reports-hero__eyebrow">Reports</p>
                <h2 class="isp-reports-hero__title">Export Clients</h2>
                <p class="isp-reports-hero__sub">
                    Download a CSV of all subscribers with contact, package, area, and open balance.
                </p>
            </div>
        </section>

        <section class="isp-reports-stats">
            <div class="isp-reports-stat">
                <span class="isp-reports-stat__label">Total clients</span>
                <strong>{{ number_format($this->customerCount) }}</strong>
            </div>
            <div class="isp-reports-stat isp-reports-stat--primary">
                <span class="isp-reports-stat__label">Active</span>
                <strong>{{ number_format($this->activeCount) }}</strong>
            </div>
        </section>

        <section class="isp-reports-info-card">
            <h3>CSV columns</h3>
            <p>Customer code, name, phone, email, status, package, area, zone, address, joined date, open invoice balance.</p>
            <p class="isp-reports-info-card__hint">Use the <strong>Download CSV</strong> button above to export.</p>
        </section>
    </div>
</x-filament-panels::page>
