<x-filament-panels::page class="isp-reseller-page">
    <div class="space-y-5">
        <section class="isp-reseller-hero isp-reseller-hero--compact">
            <div class="isp-reseller-hero__main">
                <p class="isp-reseller-hero__eyebrow">Resellers</p>
                <h2 class="isp-reseller-hero__title">Package prices</h2>
                <p class="isp-reseller-hero__sub">
                    Base package pricing and area/zone overrides used for reseller territories and billing.
                </p>
            </div>
        </section>

        <section class="isp-reseller-table-card">
            {{ $this->table }}
        </section>
    </div>
</x-filament-panels::page>
