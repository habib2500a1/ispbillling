<x-filament-panels::page class="isp-reseller-page">
    <div class="space-y-5">
        <section class="isp-reseller-hero isp-reseller-hero--compact">
            <div class="isp-reseller-hero__main">
                <p class="isp-reseller-hero__eyebrow">Resellers</p>
                <h2 class="isp-reseller-hero__title">Reseller packages & rates</h2>
                <p class="isp-reseller-hero__sub">
                    Customer bills use the package list price (e.g. 500 BDT). Set the <strong>reseller rate</strong> (e.g. 200 BDT)
                    on each reseller’s <strong>Packages &amp; reseller rate</strong> tab.
                </p>
            </div>
        </section>

        <section class="isp-reseller-table-card">
            {{ $this->table }}
        </section>
    </div>
</x-filament-panels::page>
